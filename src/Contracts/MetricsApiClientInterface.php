<?php

declare(strict_types=1);

namespace Initred\Langfuse\Contracts;

use Initred\Langfuse\Dto\MetricQuery;
use Initred\Langfuse\Dto\MetricsResponse;

interface MetricsApiClientInterface
{
    public function query(MetricQuery $query): ?MetricsResponse;
}
