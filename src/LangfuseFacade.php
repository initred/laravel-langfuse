<?php

declare(strict_types=1);

namespace Initred\Langfuse;

use Illuminate\Support\Facades\Facade;
use Initred\Langfuse\Config\LangfuseConfig;
use Initred\Langfuse\Contracts\LangfuseClientInterface;
use Initred\Langfuse\Contracts\TraceContextResolverInterface;
use Initred\Langfuse\Prompt\CurrentPromptRegistry;
use Initred\Langfuse\Testing\LangfuseFake;

/**
 * @method static \Initred\Langfuse\Objects\LangfuseTrace trace(\Initred\Langfuse\Dto\TraceBody $body)
 * @method static \Initred\Langfuse\Objects\LangfuseTrace currentTrace()
 * @method static void setCurrentTrace(\Initred\Langfuse\Objects\LangfuseTrace $trace)
 * @method static void score(\Initred\Langfuse\Dto\ScoreBody $body, ?string $timestamp = null)
 * @method static \Initred\Langfuse\Dto\ScoreResponse|null getScore(string $scoreId)
 * @method static \Initred\Langfuse\Dto\ScoreListResponse|null getScores(?\Initred\Langfuse\Dto\ScoreQuery $query = null)
 * @method static bool deleteScore(string $scoreId)
 * @method static \Initred\Langfuse\Dto\ObservationResponse|null getObservation(string $observationId, ?string $fromStartTime = null, ?string $toStartTime = null, ?string $fields = null)
 * @method static \Initred\Langfuse\Dto\ObservationListResponse|null getObservations(?\Initred\Langfuse\Dto\ObservationQuery $query = null)
 * @method static \Initred\Langfuse\Dto\MetricsResponse|null queryMetrics(\Initred\Langfuse\Dto\MetricQuery $query)
 * @method static \Initred\Langfuse\Dto\DatasetResponse|null getDataset(string $datasetName)
 * @method static \Initred\Langfuse\Dto\DatasetListResponse|null listDatasets(?int $page = null, ?int $limit = null)
 * @method static \Initred\Langfuse\Dto\DatasetResponse|null createDataset(\Initred\Langfuse\Dto\CreateDatasetBody $body)
 * @method static \Initred\Langfuse\Dto\DatasetItemResponse|null getDatasetItem(string $id)
 * @method static \Initred\Langfuse\Dto\DatasetItemListResponse|null listDatasetItems(?\Initred\Langfuse\Dto\DatasetItemQuery $query = null)
 * @method static \Initred\Langfuse\Dto\DatasetItemResponse|null createDatasetItem(\Initred\Langfuse\Dto\CreateDatasetItemBody $body)
 * @method static bool deleteDatasetItem(string $id)
 * @method static \Initred\Langfuse\Dto\ExperimentListResponse|null listExperiments(\Initred\Langfuse\Dto\ExperimentQuery $query)
 * @method static \Initred\Langfuse\Dto\ExperimentItemListResponse|null listExperimentItems(\Initred\Langfuse\Dto\ExperimentItemQuery $query)
 * @method static \Initred\Langfuse\Dto\ExperimentResponse|null getExperiment(string $experimentId, string $fromStartTime, ?string $toStartTime = null)
 * @method static void flush()
 * @method static void shutdown()
 * @method static bool isEnabled()
 * @method static \Initred\Langfuse\Contracts\PromptInterface prompt(string $name, ?int $version = null, ?string $label = null, string|array<int, array<string, string>>|null $fallback = null)
 * @method static \Initred\Langfuse\Contracts\PromptInterface|null createPrompt(\Initred\Langfuse\Dto\CreatePromptBody $body)
 * @method static \Initred\Langfuse\Dto\PromptListResponse|null listPrompts(?string $name = null, ?string $label = null, ?int $page = null, ?int $limit = null)
 *
 * @see LangfuseClient
 */
class LangfuseFacade extends Facade
{
    public static function fake(): LangfuseFake
    {
        $fake = new LangfuseFake(
            app(CurrentPromptRegistry::class),
            app(LangfuseConfig::class),
            app(TraceContextResolverInterface::class),
        );

        static::swap($fake);

        return $fake;
    }

    protected static function getFacadeAccessor(): string
    {
        return LangfuseClientInterface::class;
    }
}
