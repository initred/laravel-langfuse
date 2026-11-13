<?php

declare(strict_types=1);

namespace Initred\Langfuse\Dto;

use Initred\Langfuse\Contracts\SerializableInterface;

readonly class IngestionBatch implements SerializableInterface
{
    /**
     * @param  array<IngestionEvent>  $batch
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public array $batch,
        public array $metadata = [],
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'batch' => array_map(
                fn (IngestionEvent $event): array => $event->toArray(),
                $this->batch,
            ),
            'metadata' => (object) $this->metadata,
        ];
    }
}
