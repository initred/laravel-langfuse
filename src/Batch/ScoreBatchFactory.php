<?php

declare(strict_types=1);

namespace Initred\Langfuse\Batch;

use Initred\Langfuse\Config\LangfuseConfig;
use Initred\Langfuse\Dto\IdGenerator;
use Initred\Langfuse\Dto\IngestionBatch;
use Initred\Langfuse\Dto\IngestionEvent;
use Initred\Langfuse\Dto\ScoreBody;
use Initred\Langfuse\Enums\EventType;
use Initred\Langfuse\Version;

/**
 * Scores still travel as `score-create` events on the ingestion endpoint, which
 * v4 keeps supporting. The envelope timestamp is what identifies a score for
 * overwriting, alongside its id and name.
 */
class ScoreBatchFactory
{
    public function __construct(
        private readonly LangfuseConfig $config,
    ) {}

    public function event(ScoreBody $score, ?string $timestamp = null): IngestionEvent
    {
        return new IngestionEvent(
            id: IdGenerator::uuid(),
            type: EventType::ScoreCreate,
            timestamp: $timestamp ?? IdGenerator::timestamp(),
            body: $score,
        );
    }

    /**
     * @param  array<int, IngestionEvent>  $events
     */
    public function batch(array $events): IngestionBatch
    {
        return new IngestionBatch(
            batch: array_values($events),
            metadata: $this->metadata(count($events)),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function metadata(int $batchSize): array
    {
        return [
            'batch_size' => $batchSize,
            'sdk_name' => Version::SDK_NAME,
            'sdk_version' => Version::SDK_VERSION,
            'public_key' => $this->config->publicKey,
        ];
    }
}
