<?php

declare(strict_types=1);

namespace Initred\Langfuse\Batch;

use Initred\Langfuse\Config\LangfuseConfig;
use Initred\Langfuse\Contracts\OtelTraceApiClientInterface;
use Initred\Langfuse\Contracts\ScoreApiClientInterface;
use Initred\Langfuse\Dto\IngestionBatch;
use Initred\Langfuse\Dto\Otlp\OtlpExportRequest;
use Initred\Langfuse\Otlp\OtlpRequestFactory;

/**
 * Sends the batch inside the current process.
 */
class EventBatcher extends AbstractEventBatcher
{
    public function __construct(
        private readonly OtelTraceApiClientInterface $otelClient,
        private readonly ScoreApiClientInterface $scoreApiClient,
        LangfuseConfig $config,
        OtlpRequestFactory $requestFactory,
        ScoreBatchFactory $scoreBatchFactory,
    ) {
        parent::__construct($config, $requestFactory, $scoreBatchFactory);
    }

    protected function sendObservations(OtlpExportRequest $request): void
    {
        $this->otelClient->export($request);
    }

    protected function sendScores(IngestionBatch $batch): void
    {
        $this->scoreApiClient->ingest($batch);
    }
}
