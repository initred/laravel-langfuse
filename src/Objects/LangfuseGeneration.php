<?php

declare(strict_types=1);

namespace Initred\Langfuse\Objects;

use Illuminate\Support\Facades\Log;
use Initred\Langfuse\Contracts\EndsOnShutdownInterface;
use Initred\Langfuse\Contracts\EventBatcherInterface;
use Initred\Langfuse\Dto\GenerationBody;
use Initred\Langfuse\Dto\IdGenerator;
use Initred\Langfuse\Dto\Usage;
use Initred\Langfuse\Enums\ObservationLevel;
use Initred\Langfuse\Tracing\CompletedObservation;
use Initred\Langfuse\Tracing\TraceContext;

/**
 * A generation is built in memory and exported once, by `end()`.
 */
class LangfuseGeneration implements EndsOnShutdownInterface
{
    protected GenerationBody $body;

    protected EventBatcherInterface $batcher;

    protected TraceContext $context;

    protected ?OpenObservationRegistry $registry;

    protected bool $ended = false;

    public function __construct(
        GenerationBody $body,
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

    public function getBody(): GenerationBody
    {
        return $this->body;
    }

    public function hasEnded(): bool
    {
        return $this->ended;
    }

    public function end(
        ?string $endTime = null,
        mixed $output = null,
        ?Usage $usage = null,
        ?string $statusMessage = null,
        ?ObservationLevel $level = null,
    ): void {
        if ($this->ended) {
            Log::warning('Langfuse generation ended twice', ['observationId' => $this->getId()]);

            return;
        }

        $this->ended = true;
        $this->registry?->unregister($this);

        $this->body = $this->body->completed(
            endTime: $endTime ?? IdGenerator::timestamp(),
            output: $output,
            usage: $usage,
            statusMessage: $statusMessage,
            level: $level,
        );

        $this->batcher->enqueue(CompletedObservation::generation($this->body, $this->context));
    }

    public function endOnShutdown(): void
    {
        $this->end(statusMessage: 'ended by shutdown', level: ObservationLevel::WARNING);
    }
}
