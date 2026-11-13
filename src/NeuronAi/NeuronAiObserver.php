<?php

declare(strict_types=1);

namespace Initred\Langfuse\NeuronAi;

use DateTimeImmutable;
use DateTimeZone;
use Initred\Langfuse\Contracts\LangfuseClientInterface;
use Initred\Langfuse\Dto\GenerationBody;
use Initred\Langfuse\Dto\SpanBody;
use Initred\Langfuse\Dto\TraceBody;
use Initred\Langfuse\Dto\Usage;
use Initred\Langfuse\Enums\ObservationLevel;
use Initred\Langfuse\Enums\ObservationType;
use Initred\Langfuse\Objects\LangfuseSpan;
use Initred\Langfuse\Objects\LangfuseTrace;
use Initred\Langfuse\Objects\NullLangfuseTrace;
use Initred\Langfuse\Prompt\CurrentPromptRegistry;
use NeuronAI\Agent\Observability\InferenceStart;
use NeuronAI\Agent\Observability\InferenceStop;
use NeuronAI\Agent\Observability\ToolCalled;
use NeuronAI\Agent\Observability\ToolCalling;
use NeuronAI\Chat\Messages\Message;
use NeuronAI\Chat\Messages\Usage as NeuronUsage;
use NeuronAI\Observability\ObservabilityEvent;
use NeuronAI\RAG\Observability\Retrieved;
use NeuronAI\RAG\Observability\Retrieving;
use NeuronAI\Tools\ToolCall;
use NeuronAI\Tools\ToolOutput;
use NeuronAI\Workflow\Observability\WorkflowEnd;
use NeuronAI\Workflow\Observability\WorkflowError;
use NeuronAI\Workflow\Observability\WorkflowStart;

/**
 * PSR-14 listener for Neuron AI 4.x. Subscribe it to every observability event
 * of an agent, RAG or workflow:
 *
 *     $agent->subscribe(ObservabilityEvent::class, NeuronAiObserver::make());
 */
class NeuronAiObserver
{
    /** @var array<class-string<ObservabilityEvent>, string> */
    protected array $handlers = [
        WorkflowStart::class => 'workflowStart',
        WorkflowEnd::class => 'workflowEnd',
        WorkflowError::class => 'workflowError',
        InferenceStart::class => 'inferenceStart',
        InferenceStop::class => 'inferenceStop',
        ToolCalling::class => 'toolCalling',
        ToolCalled::class => 'toolCalled',
        Retrieving::class => 'ragRetrieving',
        Retrieved::class => 'ragRetrieved',
    ];

    private ?LangfuseTrace $trace = null;

    /** Whether this observer created the trace itself, and may therefore end it. */
    private bool $ownsTrace = false;

    /**
     * Open workflow runs. A sub-agent that shares this observer starts and ends
     * its own run inside the outer one; only the outermost end closes the trace.
     */
    private int $depth = 0;

    /** @var array<string, float> */
    private array $startTimes = [];

    /** @var array<string, LangfuseSpan> */
    private array $spans = [];

    public function __construct(
        private readonly LangfuseClientInterface $langfuse,
        private readonly CurrentPromptRegistry $prompts = new CurrentPromptRegistry,
    ) {}

    public static function make(): self
    {
        return app(self::class);
    }

    public function __invoke(ObservabilityEvent $event): void
    {
        $handler = $this->handlers[$event::class] ?? null;

        if ($handler !== null) {
            $this->{$handler}($event);
        }
    }

    protected function workflowStart(WorkflowStart $event): void
    {
        $this->getOrCreateTrace($event);
        $this->depth++;
    }

    /**
     * Neuron dispatches `WorkflowEnd` when a run completes, is suspended or fails.
     */
    protected function workflowEnd(WorkflowEnd $event): void
    {
        $this->depth = max(0, $this->depth - 1);

        if ($this->depth > 0) {
            return;
        }

        $trace = $this->getOrCreateTrace($event);

        $trace->update(new TraceBody(
            output: $event->state->all(),
        ));

        $this->endOwnedTrace($trace);

        $this->langfuse->flush();
    }

    /**
     * The error is recorded on the trace; the `WorkflowEnd` that follows ends it.
     */
    protected function workflowError(WorkflowError $event): void
    {
        $this->getOrCreateTrace($event)->update(new TraceBody(
            metadata: [
                'error' => $event->exception->getMessage(),
                'error_trace' => $event->exception->getTraceAsString(),
            ],
        ));
    }

    /**
     * Only the timing is needed: the input is read from the matching `InferenceStop`.
     */
    protected function inferenceStart(): void
    {
        $this->startTimes['inference'] = microtime(true);
    }

    protected function inferenceStop(InferenceStop $event): void
    {
        $startTime = $this->startTimes['inference'] ?? microtime(true);
        $trace = $this->getOrCreateTrace($event);
        $response = $event->response->message();

        $body = new GenerationBody(
            name: 'inference',
            startTime: $this->formatTime($startTime),
            input: $event->message instanceof Message ? $event->message->getContent() : null,
            model: $event->provider->getModel(),
        );

        $generation = $trace->generation($body->withPrompt($this->prompts->consume()));

        $generation->end(
            endTime: $this->formatTime(microtime(true)),
            output: $response->getContent(),
            usage: $this->mapUsage($response->getUsage()),
        );

        unset($this->startTimes['inference']);
    }

    protected function toolCalling(ToolCalling $event): void
    {
        $key = $this->toolKey($event->tool);
        $this->startTimes[$key] = microtime(true);

        $this->spans[$key] = $this->getOrCreateTrace($event)->span(new SpanBody(
            name: "tool-{$event->tool->getName()}",
            startTime: $this->formatTime($this->startTimes[$key]),
            input: $event->tool->getInputs(),
            type: ObservationType::Tool,
        ));
    }

    protected function toolCalled(ToolCalled $event): void
    {
        $key = $this->toolKey($event->tool);
        $span = $this->spans[$key] ?? null;

        if ($span === null) {
            return;
        }

        $result = $event->tool->hasResult() ? $event->tool->getResult() : null;

        $span->end(
            endTime: $this->formatTime(microtime(true)),
            output: $result instanceof ToolOutput ? $result->getText() : $result,
            level: $this->toolLevel($result),
        );

        unset($this->spans[$key], $this->startTimes[$key]);
    }

    private function toolLevel(string|ToolOutput|null $result): ?ObservationLevel
    {
        return $result instanceof ToolOutput && $result->isError() ? ObservationLevel::ERROR : null;
    }

    protected function ragRetrieving(Retrieving $event): void
    {
        $this->startTimes['rag'] = microtime(true);

        $this->spans['rag'] = $this->getOrCreateTrace($event)->span(new SpanBody(
            name: 'rag-retrieval',
            startTime: $this->formatTime($this->startTimes['rag']),
            input: $event->question->getContent(),
            type: ObservationType::Retriever,
        ));
    }

    protected function ragRetrieved(Retrieved $event): void
    {
        $span = $this->spans['rag'] ?? null;

        if ($span === null) {
            return;
        }

        $span->end(
            endTime: $this->formatTime(microtime(true)),
            output: [
                'question' => $event->question->getContent(),
                'documents' => count($event->documents),
            ],
        );

        unset($this->spans['rag'], $this->startTimes['rag']);
    }

    /**
     * A tool call is matched to its result by call ID, so parallel calls of the
     * same tool each get their own span.
     */
    private function toolKey(ToolCall $call): string
    {
        return 'tool-'.($call->getCallId() ?? $call->getName());
    }

    /**
     * Neuron's input count includes the tokens read from a prompt cache. Langfuse
     * counts every token in exactly one usage key, so they move to their own key.
     */
    private function mapUsage(?NeuronUsage $usage): ?Usage
    {
        if ($usage === null) {
            return null;
        }

        $cached = $usage->cachedInputTokens;

        return new Usage(
            input: max(0, $usage->inputTokens - $cached),
            output: $usage->outputTokens,
            total: $usage->getTotal(),
            details: $cached > 0 ? ['input_cached_tokens' => $cached] : null,
        );
    }

    /**
     * The root observation is exported by `end()`. A trace adopted from the
     * middleware or from manual tracing belongs to its own owner and is left open.
     */
    private function endOwnedTrace(LangfuseTrace $trace): void
    {
        if ($this->ownsTrace) {
            $trace->end();
            $this->langfuse->setCurrentTrace(new NullLangfuseTrace);
        }

        $this->trace = null;
        $this->ownsTrace = false;
    }

    private function getOrCreateTrace(ObservabilityEvent $event): LangfuseTrace
    {
        if ($this->trace !== null) {
            return $this->trace;
        }

        $existing = $this->langfuse->currentTrace();

        if (! $existing instanceof NullLangfuseTrace) {
            return $this->trace = $existing;
        }

        $name = $this->getShortClassName($event->source ?? $event);

        $trace = $this->langfuse->trace(new TraceBody(
            name: "neuron-ai-{$name}",
            metadata: [
                'source' => 'neuron-ai-auto-instrumentation',
            ],
        ));

        $this->langfuse->setCurrentTrace($trace);
        $this->trace = $trace;
        $this->ownsTrace = true;

        return $trace;
    }

    private function getShortClassName(object $object): string
    {
        $parts = explode('\\', get_class($object));

        return end($parts);
    }

    private function formatTime(float $microtime): string
    {
        $dt = DateTimeImmutable::createFromFormat('U.u', sprintf('%.6f', $microtime));

        if ($dt === false) {
            return now()->toIso8601ZuluString();
        }

        return $dt->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d\TH:i:s.u\Z');
    }
}
