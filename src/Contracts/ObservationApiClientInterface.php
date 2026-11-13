<?php

declare(strict_types=1);

namespace Initred\Langfuse\Contracts;

use Initred\Langfuse\Dto\ObservationListResponse;
use Initred\Langfuse\Dto\ObservationQuery;
use Initred\Langfuse\Dto\ObservationResponse;

interface ObservationApiClientInterface
{
    public function get(
        string $observationId,
        ?string $fromStartTime = null,
        ?string $toStartTime = null,
        ?string $fields = null,
    ): ?ObservationResponse;

    public function getMany(?ObservationQuery $query = null): ?ObservationListResponse;
}
