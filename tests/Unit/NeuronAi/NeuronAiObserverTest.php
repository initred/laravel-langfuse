<?php

declare(strict_types=1);

use Initred\Langfuse\Config\LangfuseConfig;
use Initred\Langfuse\Dto\TextPrompt;
use Initred\Langfuse\Dto\TraceBody;
use Initred\Langfuse\NeuronAi\NeuronAiObserver;
use Initred\Langfuse\Objects\LangfuseGeneration;
use Initred\Langfuse\Objects\LangfuseSpan;
use Initred\Langfuse\Objects\LangfuseTrace;
use Initred\Langfuse\Objects\NullLangfuseTrace;
use Initred\Langfuse\Prompt\CurrentPromptRegistry;
use Initred\Langfuse\Testing\LangfuseFake;
use NeuronAI\Agent\Observability\InferenceStart;
use NeuronAI\Agent\Observability\InferenceStop;
use NeuronAI\Agent\Observability\ToolCalled;
use NeuronAI\Agent\Observability\ToolCalling;
use NeuronAI\Chat\Messages\AssistantMessage;
use NeuronAI\Chat\Messages\Usage;
use NeuronAI\Chat\Messages\UserMessage;
use NeuronAI\Observability\ObservabilityEvent;
use NeuronAI\Providers\ProviderResponse;
use NeuronAI\RAG\Observability\Retrieved;
use NeuronAI\RAG\Observability\Retrieving;
use NeuronAI\Testing\FakeAIProvider;
use NeuronAI\Tools\ToolCall;
use NeuronAI\Tools\ToolOutput;
use NeuronAI\Workflow\Observability\WorkflowEnd;
use NeuronAI\Workflow\Observability\WorkflowError;
use NeuronAI\Workflow\Observability\WorkflowNodeStart;
use NeuronAI\Workflow\Observability\WorkflowStart;
use NeuronAI\Workflow\WorkflowState;

function makeNeuronLangfuseClient(): LangfuseFake
{
    return new LangfuseFake(
        new CurrentPromptRegistry,
        new LangfuseConfig(publicKey: 'pk', secretKey: 'sk'),
    );
}

/**
 * @return array<int, array<string, mixed>>
 */
function neuronTraceBodies(LangfuseFake $fake): array
{
    return array_map(fn (LangfuseTrace $trace): array => $trace->getBody()->toArray(), $fake->traces());
}

/**
 * @return array<int, array<string, mixed>>
 */
function neuronGenerationBodies(LangfuseFake $fake): array
{
    return array_map(fn (LangfuseGeneration $g): array => $g->getBody()->toArray(), $fake->generations());
}

/**
 * @return array<int, array<string, mixed>>
 */
function neuronSpanBodies(LangfuseFake $fake): array
{
    return array_map(fn (LangfuseSpan $s): array => $s->getBody()->toArray(), $fake->spans());
}

class TestNeuronAgent {}

/**
 * Stamps the emitting component on the event, as Neuron does at dispatch time.
 *
 * @template T of ObservabilityEvent
 *
 * @param  T  $event
 * @return T
 */
function neuronEvent(ObservabilityEvent $event, ?object $source = null): ObservabilityEvent
{
    $event->source = $source ?? new TestNeuronAgent;

    return $event;
}

function neuronInferenceStop(string $input, string $output, ?Usage $usage = null, UserMessage|false|null $message = null): InferenceStop
{
    $response = new AssistantMessage($output);

    if ($usage !== null) {
        $response->setUsage($usage);
    }

    return neuronEvent(new InferenceStop(
        $message ?? new UserMessage($input),
        new ProviderResponse($response),
        new FakeAIProvider,
    ));
}

function neuronToolCall(string $name = 'search', ?string $callId = 'call_1', mixed $result = null): ToolCall
{
    $call = new ToolCall(name: $name, callId: $callId, inputs: ['query' => 'PHP']);

    if ($result !== null) {
        $call->setResult($result);
    }

    return $call;
}

function neuronWorkflowEnd(array $state = []): WorkflowEnd
{
    return neuronEvent(new WorkflowEnd(new WorkflowState($state)));
}

it('ignores events it does not handle', function () {
    $fake = makeNeuronLangfuseClient();
    $observer = new NeuronAiObserver($fake);

    $observer(neuronEvent(new WorkflowNodeStart('SomeNode', new WorkflowState)));

    expect($fake->traces())->toBeEmpty();
});

it('creates trace on workflow-start without sending anything', function () {
    $fake = makeNeuronLangfuseClient();
    $observer = new NeuronAiObserver($fake);

    $observer(neuronEvent(new WorkflowStart([])));

    $body = neuronTraceBodies($fake)[0];

    expect($fake->traces())->toHaveCount(1)
        ->and($body['name'])->toBe('neuron-ai-TestNeuronAgent')
        ->and($body['metadata']['source'])->toBe('neuron-ai-auto-instrumentation')
        ->and($fake->recorder()->observations())->toBeEmpty();
});

it('creates and ends a generation on inference events', function () {
    $fake = makeNeuronLangfuseClient();
    $observer = new NeuronAiObserver($fake);

    $observer(neuronEvent(new WorkflowStart([])));
    $observer(neuronEvent(new InferenceStart(new UserMessage('What is PHP?'))));
    $observer(neuronInferenceStop('What is PHP?', 'PHP is a programming language'));

    $body = neuronGenerationBodies($fake)[0];

    expect($fake->generations())->toHaveCount(1)
        ->and($fake->generations()[0]->hasEnded())->toBeTrue()
        ->and($body['name'])->toBe('inference')
        ->and($body['model'])->toBe('fake')
        ->and($body['input'])->toBe('What is PHP?')
        ->and($body['output'])->toBe('PHP is a programming language');
});

it('captures token usage in generation', function () {
    $fake = makeNeuronLangfuseClient();
    $observer = new NeuronAiObserver($fake);

    $observer(neuronInferenceStop('Hello', 'Hi there', new Usage(inputTokens: 10, outputTokens: 20)));

    $body = neuronGenerationBodies($fake)[0];

    expect($body['usage']['input'])->toBe(10)
        ->and($body['usage']['output'])->toBe(20)
        ->and($body['usage']['total'])->toBe(30)
        ->and($body['usage'])->not->toHaveKey('details');
});

it('moves cached input tokens out of the input count', function () {
    $fake = makeNeuronLangfuseClient();
    $observer = new NeuronAiObserver($fake);

    $observer(neuronInferenceStop('Hello', 'Hi', new Usage(inputTokens: 180, outputTokens: 20, cachedInputTokens: 30)));

    $usage = neuronGenerationBodies($fake)[0]['usage'];

    expect($usage['input'])->toBe(150)
        ->and($usage['output'])->toBe(20)
        ->and($usage['total'])->toBe(200)
        ->and($usage['details'])->toBe(['input_cached_tokens' => 30]);
});

it('handles inference-stop with false message', function () {
    $fake = makeNeuronLangfuseClient();
    $observer = new NeuronAiObserver($fake);

    $observer(neuronInferenceStop('input', 'Response text', message: false));

    expect(neuronGenerationBodies($fake)[0])->not->toHaveKey('input');
});

it('creates and ends a span for tool events', function () {
    $fake = makeNeuronLangfuseClient();
    $observer = new NeuronAiObserver($fake);

    $observer(neuronEvent(new WorkflowStart([])));
    $observer(neuronEvent(new ToolCalling(neuronToolCall('web-search'))));
    $observer(neuronEvent(new ToolCalled(neuronToolCall('web-search', result: 'Search results'))));

    $body = neuronSpanBodies($fake)[0];

    expect($fake->spans())->toHaveCount(1)
        ->and($fake->spans()[0]->hasEnded())->toBeTrue()
        ->and($body['name'])->toBe('tool-web-search')
        ->and($body['type'])->toBe('tool')
        ->and($body['input'])->toBe(['query' => 'PHP'])
        ->and($body['output'])->toBe('Search results');
});

it('keeps parallel calls of the same tool apart by call id', function () {
    $fake = makeNeuronLangfuseClient();
    $observer = new NeuronAiObserver($fake);

    $observer(neuronEvent(new ToolCalling(neuronToolCall('search', 'call_1'), true)));
    $observer(neuronEvent(new ToolCalling(neuronToolCall('search', 'call_2'), true)));
    $observer(neuronEvent(new ToolCalled(neuronToolCall('search', 'call_2', 'second'))));
    $observer(neuronEvent(new ToolCalled(neuronToolCall('search', 'call_1', 'first'))));

    $bodies = neuronSpanBodies($fake);

    expect($fake->spans())->toHaveCount(2)
        ->and($bodies[0]['output'])->toBe('first')
        ->and($bodies[1]['output'])->toBe('second');
});

it('records a tool output as text and marks a failed one as an error', function () {
    $fake = makeNeuronLangfuseClient();
    $observer = new NeuronAiObserver($fake);

    $observer(neuronEvent(new ToolCalling(neuronToolCall('search'))));
    $observer(neuronEvent(new ToolCalled(neuronToolCall('search', result: ToolOutput::error('No such order')))));

    $body = neuronSpanBodies($fake)[0];

    expect($body['output'])->toBe('No such order')
        ->and($body['level'])->toBe('ERROR');
});

it('ends a tool span without output when the call has no result', function () {
    $fake = makeNeuronLangfuseClient();
    $observer = new NeuronAiObserver($fake);

    $observer(neuronEvent(new ToolCalling(neuronToolCall('search'))));
    $observer(neuronEvent(new ToolCalled(neuronToolCall('search'))));

    expect($fake->spans()[0]->hasEnded())->toBeTrue()
        ->and(neuronSpanBodies($fake)[0])->not->toHaveKey('output');
});

it('handles tool-called without prior tool-calling gracefully', function () {
    $fake = makeNeuronLangfuseClient();
    $observer = new NeuronAiObserver($fake);

    $observer(neuronEvent(new WorkflowStart([])));
    $observer(neuronEvent(new ToolCalled(neuronToolCall('search', result: 'x'))));

    expect($fake->spans())->toBeEmpty();
});

it('creates and ends a span for RAG retrieval events', function () {
    $fake = makeNeuronLangfuseClient();
    $observer = new NeuronAiObserver($fake);
    $question = new UserMessage('What is Laravel?');

    $observer(neuronEvent(new WorkflowStart([])));
    $observer(neuronEvent(new Retrieving($question)));
    $observer(neuronEvent(new Retrieved($question, ['doc1', 'doc2'])));

    $body = neuronSpanBodies($fake)[0];

    expect($fake->spans())->toHaveCount(1)
        ->and($body['name'])->toBe('rag-retrieval')
        ->and($body['type'])->toBe('retriever')
        ->and($body['input'])->toBe('What is Laravel?')
        ->and($body['output'])->toBe([
            'question' => 'What is Laravel?',
            'documents' => 2,
        ]);
});

it('records the error on the trace and ends it with the workflow', function () {
    $fake = makeNeuronLangfuseClient();
    $observer = new NeuronAiObserver($fake);

    $observer(neuronEvent(new WorkflowStart([])));
    $observer(neuronEvent(new WorkflowError(new RuntimeException('Something went wrong'), false)));

    expect($fake->traces()[0]->hasEnded())->toBeFalse();

    $observer(neuronWorkflowEnd());

    $body = neuronTraceBodies($fake)[0];

    expect($fake->traces())->toHaveCount(1)
        ->and($body['metadata']['error'])->toBe('Something went wrong')
        ->and($body['metadata'])->toHaveKey('error_trace')
        ->and($body['metadata']['source'])->toBe('neuron-ai-auto-instrumentation')
        ->and($fake->traces()[0]->hasEnded())->toBeTrue();
});

it('reuses existing trace from langfuse client', function () {
    $fake = makeNeuronLangfuseClient();

    (new NeuronAiObserver($fake))(neuronEvent(new WorkflowStart([])));
    (new NeuronAiObserver($fake))(neuronEvent(new WorkflowStart([])));

    expect($fake->traces())->toHaveCount(1);
});

it('leaves an adopted trace open for its owner', function () {
    $fake = makeNeuronLangfuseClient();

    $trace = $fake->trace(new TraceBody(name: 'workflow'));
    $fake->setCurrentTrace($trace);

    $observer = new NeuronAiObserver($fake);
    $observer(neuronEvent(new WorkflowStart([])));
    $observer(neuronWorkflowEnd(['result' => 'success']));

    expect($trace->hasEnded())->toBeFalse()
        ->and($fake->currentTrace())->toBe($trace);
});

it('sets current trace on langfuse client', function () {
    $fake = makeNeuronLangfuseClient();
    $observer = new NeuronAiObserver($fake);

    $observer(neuronEvent(new WorkflowStart([])));

    expect($fake->currentTrace())->not->toBeInstanceOf(NullLangfuseTrace::class);
});

it('sets the trace output and ends the owned trace on workflow-end', function () {
    $fake = makeNeuronLangfuseClient();
    $observer = new NeuronAiObserver($fake);

    $observer(neuronEvent(new WorkflowStart([])));
    $observer(neuronWorkflowEnd(['result' => 'success']));

    $body = neuronTraceBodies($fake)[0];

    expect($body['output'])->toBe(['result' => 'success'])
        ->and($fake->traces()[0]->hasEnded())->toBeTrue()
        ->and($fake->currentTrace())->toBeInstanceOf(NullLangfuseTrace::class);
});

it('keeps the trace open until the outermost workflow ends', function () {
    $fake = makeNeuronLangfuseClient();
    $observer = new NeuronAiObserver($fake);

    $observer(neuronEvent(new WorkflowStart([])));
    $observer(neuronEvent(new WorkflowStart([])));
    $observer(neuronWorkflowEnd(['inner' => true]));

    expect($fake->traces()[0]->hasEnded())->toBeFalse();

    $observer(neuronWorkflowEnd(['outer' => true]));

    expect($fake->traces())->toHaveCount(1)
        ->and($fake->traces()[0]->hasEnded())->toBeTrue()
        ->and(neuronTraceBodies($fake)[0]['output'])->toBe(['outer' => true]);
});

it('starts a new trace for the next run', function () {
    $fake = makeNeuronLangfuseClient();
    $observer = new NeuronAiObserver($fake);

    foreach ([1, 2] as $_) {
        $observer(neuronEvent(new WorkflowStart([])));
        $observer(neuronWorkflowEnd());
    }

    expect($fake->traces())->toHaveCount(2)
        ->and($fake->traces()[0]->hasEnded())->toBeTrue()
        ->and($fake->traces()[1]->hasEnded())->toBeTrue();
});

it('handles full workflow with inference and tools', function () {
    $fake = makeNeuronLangfuseClient();
    $observer = new NeuronAiObserver($fake);

    $observer(neuronEvent(new WorkflowStart([])));
    $observer(neuronEvent(new InferenceStart(new UserMessage('Search for PHP info'))));
    $observer(neuronInferenceStop('Search for PHP info', 'Here is the info', new Usage(inputTokens: 50, outputTokens: 100)));
    $observer(neuronEvent(new ToolCalling(neuronToolCall('web-search'))));
    $observer(neuronEvent(new ToolCalled(neuronToolCall('web-search', result: 'PHP info'))));
    $observer(neuronWorkflowEnd(['done' => true]));

    $fake->assertTraceCreated()
        ->assertTraceEnded()
        ->assertGenerationEnded('inference')
        ->assertSpanEnded('tool-web-search')
        ->assertEventCount(3);
});

it('nests the inference generation under the root observation', function () {
    $fake = makeNeuronLangfuseClient();
    $observer = new NeuronAiObserver($fake);

    $observer(neuronEvent(new WorkflowStart([])));
    $observer(neuronInferenceStop('What is PHP?', 'A language'));

    expect($fake->generations()[0]->getBody()->parentObservationId)
        ->toBe($fake->traces()[0]->getRootObservationId());
});

it('links the registered managed prompt to the inference generation', function () {
    $fake = makeNeuronLangfuseClient();
    $registry = new CurrentPromptRegistry;
    $registry->set(new TextPrompt(name: 'movie-critic', version: 7, prompt: 'text'));
    $observer = new NeuronAiObserver($fake, $registry);

    foreach ([1, 2] as $_) {
        $observer(neuronEvent(new InferenceStart(new UserMessage('What is PHP?'))));
        $observer(neuronInferenceStop('What is PHP?', 'A language'));
    }

    $bodies = neuronGenerationBodies($fake);

    expect($bodies[0]['promptName'])->toBe('movie-critic')
        ->and($bodies[0]['promptVersion'])->toBe(7)
        ->and($bodies[1])->not->toHaveKey('promptName')
        ->and($registry->current())->toBeNull();
});
