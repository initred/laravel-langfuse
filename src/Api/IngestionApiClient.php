<?php

declare(strict_types=1);

namespace Initred\Langfuse\Api;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Initred\Langfuse\Config\LangfuseConfig;
use Initred\Langfuse\Contracts\IngestionApiClientInterface;
use Initred\Langfuse\Dto\IngestionBatch;
use Initred\Langfuse\Dto\IngestionResponse;
use Throwable;

class IngestionApiClient implements IngestionApiClientInterface
{
    public function __construct(
        private readonly LangfuseConfig $config,
    ) {}

    public function send(IngestionBatch $batch): ?IngestionResponse
    {
        return $this->sendRaw($batch->toArray());
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function sendRaw(array $payload): ?IngestionResponse
    {
        try {
            return $this->doSend($payload);
        } catch (Throwable $throwable) {
            Log::warning('Langfuse ingestion error', ['message' => $throwable->getMessage()]);

            return null;
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function doSend(array $payload): ?IngestionResponse
    {
        $response = Http::withHeaders([
            'Authorization' => $this->config->authHeader(),
            'Content-Type' => 'application/json',
        ])
            ->timeout($this->config->requestTimeout)
            ->post($this->config->ingestionUrl(), $payload);

        if (! $response->successful()) {
            Log::warning('Langfuse ingestion failed', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return null;
        }

        /** @var array<string, mixed> $data */
        $data = $response->json() ?? [];

        $result = IngestionResponse::fromArray($data);

        $this->logErrors($result);

        return $result;
    }

    private function logErrors(IngestionResponse $response): void
    {
        if (! $response->hasErrors()) {
            return;
        }

        foreach ($response->errors as $error) {
            Log::warning('Langfuse ingestion event error', [
                'id' => $error->id,
                'status' => $error->status,
                'message' => $error->message,
                'error' => $error->error ?? null,
            ]);
        }
    }
}
