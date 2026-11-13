<?php

declare(strict_types=1);

namespace Initred\Langfuse\Contracts;

use Initred\Langfuse\Dto\IngestionBatch;
use Initred\Langfuse\Dto\IngestionResponse;
use Initred\Langfuse\Dto\ScoreListResponse;
use Initred\Langfuse\Dto\ScoreQuery;
use Initred\Langfuse\Dto\ScoreResponse;

interface ScoreApiClientInterface
{
    /**
     * Writes a batch of `score-create` events.
     */
    public function ingest(IngestionBatch $batch): ?IngestionResponse;

    public function get(string $scoreId): ?ScoreResponse;

    public function getMany(?ScoreQuery $query = null): ?ScoreListResponse;

    public function delete(string $scoreId): bool;
}
