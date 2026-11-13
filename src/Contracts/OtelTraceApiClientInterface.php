<?php

declare(strict_types=1);

namespace Initred\Langfuse\Contracts;

use Initred\Langfuse\Dto\OtelExportResult;
use Initred\Langfuse\Dto\Otlp\OtlpExportRequest;

interface OtelTraceApiClientInterface
{
    public function export(OtlpExportRequest $request): OtelExportResult;

    /**
     * @param  array<string, mixed>  $payload
     */
    public function exportRaw(array $payload): OtelExportResult;
}
