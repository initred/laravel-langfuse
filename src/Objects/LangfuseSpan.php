<?php

declare(strict_types=1);

namespace Initred\Langfuse\Objects;

use Illuminate\Support\Facades\Log;
use Initred\Langfuse\Contracts\EndsOnShutdownInterface;
use Initred\Langfuse\Contracts\EventBatcherInterface;
use Initred\Langfuse\Dto\EventBody;
use Initred\Langfuse\Dto\GenerationBody;
use Initred\Langfuse\Dto\IdGenerator;
use Initred\Langfuse\Dto\SpanBody;
use Initred\Langfuse\Enums\ObservationLevel;
use Initred\Langfuse\Tracing\CompletedObservation;
use Initred\Langfuse\Tracing\TraceContext;

/**
 * A span is built in memory and exported once, by `end()`. It does not appear in
 * Langfuse before that.
 */
class LangfuseSpan implements EndsOnShutdownInterface
{
    protected SpanBody $body;

    protected EventBatcherInterface $batcher;

    protected TraceContext $context;

    protected ?OpenObservationRegistry $registry;

    protected bool $ended = false;

    public function __construct(
        SpanBody $body,
        EventBatcherInterface $batcher,
        TraceContext $context,
        ?OpenObservationRegistry $registry = null,
    ) {
        $this->body = $body->startedAt(IdGenerator::timestamp());
        $this->batcher = $batcher;
        $this->context = $context;
        $this->registry = $registry;

        $this->registry?->register($this);
    }

    public function getId(): string
    {
        return $this->body->id;
    }

    public function getTraceId(): ?string
    {
        return $this->body->traceId;
    }

    public function getBody(): SpanBody
    {
        return $this->body;
    }

    public function hasEnded(): bool
    {
        return $this->ended;
    }

    public function span(SpanBody $span): self
    {
        return new self(
            body: $span
                ->withContext($this->context->traceId(), $this->body->id)
                ->withEnvironment($this->body->environment),
            batcher: $this->batcher,
            context: $this->context,
            registry: $this->registry,
        );
    }

    public function generation(GenerationBody $generation): LangfuseGeneration
    {
        return new LangfuseGeneration(
            body: $generation
                ->withContext($this->context->traceId(), $this->body->id)
                ->withEnvironment($this->body->environment),
            batcher: $this->batcher,
            context: $this->context,
            registry: $this->registry,
        );
    }

    public function event(EventBody $event): void
    {
        $body = $event
            ->withContext($this->context->traceId(), $this->body->id)
            ->withEnvironment($this->body->environment);

        $this->batcher->enqueue(CompletedObservation::event($body, $this->context));
    }

    public function end(
        ?string $endTime = null,
        mixed $output = null,
        ?string $statusMessage = null,
        ?ObservationLevel $level = null,
    ): void {
        if ($this->ended) {
            Log::warning('Langfuse span ended twice', ['observationId' => $this->getId()]);

            return;
        }

        $this->ended = true;
        $this->registry?->unregister($this);

        $this->body = $this->body->completed(
            endTime: $endTime ?? IdGenerator::timestamp(),
            output: $output,
            statusMessage: $statusMessage,
            level: $level,
        );

        $this->batcher->enqueue(CompletedObservation::span($this->body, $this->context));
    }

    public function endOnShutdown(): void
    {
        $this->end(statusMessage: 'ended by shutdown', level: ObservationLevel::WARNING);
    }
}
