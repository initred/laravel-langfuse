<?php

declare(strict_types=1);

namespace Initred\Langfuse\Batch;

use Initred\Langfuse\Dto\IngestionBatch;
use Initred\Langfuse\Dto\Otlp\OtlpExportRequest;
use Initred\Langfuse\Jobs\SendIngestionBatchJob;
use Initred\Langfuse\Jobs\SendOtelBatchJob;

/**
 * Hands the serialised payloads to the queue. The 4 MB split happens before
 * dispatch, so a queued payload stays bounded.
 */
class QueuedEventBatcher extends AbstractEventBatcher
{
    protected function sendObservations(OtlpExportRequest $request): void
    {
        SendOtelBatchJob::dispatch($request->toArray(), $request->spanCount())
            ->onQueue($this->config->queue);
    }

    protected function sendScores(IngestionBatch $batch): void
    {
        SendIngestionBatchJob::dispatch($batch->toArray())
            ->onQueue($this->config->queue);
    }
}
