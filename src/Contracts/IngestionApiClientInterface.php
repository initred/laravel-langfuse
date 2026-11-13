<?php

declare(strict_types=1);

namespace Initred\Langfuse\Contracts;

use Initred\Langfuse\Dto\IngestionBatch;
use Initred\Langfuse\Dto\IngestionResponse;

interface IngestionApiClientInterface
{
    public function send(IngestionBatch $batch): ?IngestionResponse;

    /**
     * @param  array<string, mixed>  $payload
     */
    public function sendRaw(array $payload): ?IngestionResponse;
}
