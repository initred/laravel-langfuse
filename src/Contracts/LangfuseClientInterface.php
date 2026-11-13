<?php

declare(strict_types=1);

namespace Initred\Langfuse\Contracts;

use Initred\Langfuse\Dto\CreateDatasetBody;
use Initred\Langfuse\Dto\CreateDatasetItemBody;
use Initred\Langfuse\Dto\CreatePromptBody;
use Initred\Langfuse\Dto\DatasetItemListResponse;
use Initred\Langfuse\Dto\DatasetItemQuery;
use Initred\Langfuse\Dto\DatasetItemResponse;
use Initred\Langfuse\Dto\DatasetListResponse;
use Initred\Langfuse\Dto\DatasetResponse;
use Initred\Langfuse\Dto\ExperimentItemListResponse;
use Initred\Langfuse\Dto\ExperimentItemQuery;
use Initred\Langfuse\Dto\ExperimentListResponse;
use Initred\Langfuse\Dto\ExperimentQuery;
use Initred\Langfuse\Dto\ExperimentResponse;
use Initred\Langfuse\Dto\MetricQuery;
use Initred\Langfuse\Dto\MetricsResponse;
use Initred\Langfuse\Dto\ObservationListResponse;
use Initred\Langfuse\Dto\ObservationQuery;
use Initred\Langfuse\Dto\ObservationResponse;
use Initred\Langfuse\Dto\PromptListResponse;
use Initred\Langfuse\Dto\ScoreBody;
use Initred\Langfuse\Dto\ScoreListResponse;
use Initred\Langfuse\Dto\ScoreQuery;
use Initred\Langfuse\Dto\ScoreResponse;
use Initred\Langfuse\Dto\TraceBody;
use Initred\Langfuse\Objects\LangfuseTrace;

interface LangfuseClientInterface
{
    public function trace(TraceBody $body): LangfuseTrace;

    public function currentTrace(): LangfuseTrace;

    public function setCurrentTrace(LangfuseTrace $trace): void;

    /**
     * `$timestamp` sets the ingestion envelope timestamp, which together with
     * the score id and name decides whether a score overwrites an earlier one.
     */
    public function score(ScoreBody $body, ?string $timestamp = null): void;

    public function getScore(string $scoreId): ?ScoreResponse;

    public function getScores(?ScoreQuery $query = null): ?ScoreListResponse;

    public function deleteScore(string $scoreId): bool;

    public function getObservation(
        string $observationId,
        ?string $fromStartTime = null,
        ?string $toStartTime = null,
        ?string $fields = null,
    ): ?ObservationResponse;

    public function getObservations(?ObservationQuery $query = null): ?ObservationListResponse;

    public function queryMetrics(MetricQuery $query): ?MetricsResponse;

    public function getDataset(string $datasetName): ?DatasetResponse;

    public function listDatasets(?int $page = null, ?int $limit = null): ?DatasetListResponse;

    public function createDataset(CreateDatasetBody $body): ?DatasetResponse;

    public function getDatasetItem(string $id): ?DatasetItemResponse;

    public function listDatasetItems(?DatasetItemQuery $query = null): ?DatasetItemListResponse;

    public function createDatasetItem(CreateDatasetItemBody $body): ?DatasetItemResponse;

    public function deleteDatasetItem(string $id): bool;

    public function listExperiments(ExperimentQuery $query): ?ExperimentListResponse;

    public function listExperimentItems(ExperimentItemQuery $query): ?ExperimentItemListResponse;

    public function getExperiment(string $experimentId, string $fromStartTime, ?string $toStartTime = null): ?ExperimentResponse;

    public function flush(): void;

    /**
     * Ends every observation that is still open and flushes. This is what the
     * application terminate hook calls; call it yourself in long-running
     * processes such as queue workers and CLI commands.
     */
    public function shutdown(): void;

    public function isEnabled(): bool;

    /**
     * @param  string|array<int, array<string, string>>|null  $fallback
     */
    public function prompt(
        string $name,
        ?int $version = null,
        ?string $label = null,
        string|array|null $fallback = null,
    ): PromptInterface;

    public function createPrompt(CreatePromptBody $body): ?PromptInterface;

    public function listPrompts(?string $name = null, ?string $label = null, ?int $page = null, ?int $limit = null): ?PromptListResponse;
}
