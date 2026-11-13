<?php

declare(strict_types=1);

namespace Initred\Langfuse\Contracts;

use Initred\Langfuse\Dto\ExperimentItemListResponse;
use Initred\Langfuse\Dto\ExperimentItemQuery;
use Initred\Langfuse\Dto\ExperimentListResponse;
use Initred\Langfuse\Dto\ExperimentQuery;
use Initred\Langfuse\Dto\ExperimentResponse;

interface ExperimentApiClientInterface
{
    public function listExperiments(ExperimentQuery $query): ?ExperimentListResponse;

    public function listExperimentItems(ExperimentItemQuery $query): ?ExperimentItemListResponse;

    public function getExperiment(string $experimentId, string $fromStartTime, ?string $toStartTime = null): ?ExperimentResponse;
}
