<?php

declare(strict_types=1);

namespace Initred\Langfuse\Contracts;

use Initred\Langfuse\Dto\CreateDatasetItemBody;
use Initred\Langfuse\Dto\DatasetItemListResponse;
use Initred\Langfuse\Dto\DatasetItemQuery;
use Initred\Langfuse\Dto\DatasetItemResponse;

interface DatasetItemApiClientInterface
{
    public function get(string $id): ?DatasetItemResponse;

    public function list(?DatasetItemQuery $query = null): ?DatasetItemListResponse;

    public function create(CreateDatasetItemBody $body): ?DatasetItemResponse;

    public function delete(string $id): bool;
}
