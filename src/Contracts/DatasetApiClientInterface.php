<?php

declare(strict_types=1);

namespace Initred\Langfuse\Contracts;

use Initred\Langfuse\Dto\CreateDatasetBody;
use Initred\Langfuse\Dto\DatasetListResponse;
use Initred\Langfuse\Dto\DatasetResponse;

interface DatasetApiClientInterface
{
    public function get(string $datasetName): ?DatasetResponse;

    public function list(?int $page = null, ?int $limit = null): ?DatasetListResponse;

    public function create(CreateDatasetBody $body): ?DatasetResponse;
}
