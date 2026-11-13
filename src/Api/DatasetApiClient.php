<?php

declare(strict_types=1);

namespace Initred\Langfuse\Api;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Initred\Langfuse\Config\LangfuseConfig;
use Initred\Langfuse\Contracts\DatasetApiClientInterface;
use Initred\Langfuse\Dto\CreateDatasetBody;
use Initred\Langfuse\Dto\DatasetListResponse;
use Initred\Langfuse\Dto\DatasetResponse;
use Throwable;

class DatasetApiClient implements DatasetApiClientInterface
{
    public function __construct(
        private readonly LangfuseConfig $config,
    ) {}

    public function get(string $datasetName): ?DatasetResponse
    {
        try {
            return $this->doGet($datasetName);
        } catch (Throwable $throwable) {
            Log::warning('Langfuse dataset fetch error', ['message' => $throwable->getMessage()]);

            return null;
        }
    }

    public function list(?int $page = null, ?int $limit = null): ?DatasetListResponse
    {
        try {
            return $this->doList($page, $limit);
        } catch (Throwable $throwable) {
            Log::warning('Langfuse dataset list error', ['message' => $throwable->getMessage()]);

            return null;
        }
    }

    public function create(CreateDatasetBody $body): ?DatasetResponse
    {
        try {
            return $this->doCreate($body);
        } catch (Throwable $throwable) {
            Log::warning('Langfuse dataset create error', ['message' => $throwable->getMessage()]);

            return null;
        }
    }

    private function doGet(string $datasetName): ?DatasetResponse
    {
        $response = Http::withHeaders([
            'Authorization' => $this->config->authHeader(),
            'Content-Type' => 'application/json',
        ])
            ->timeout($this->config->requestTimeout)
            ->get($this->config->datasetsUrl($datasetName));

        if (! $response->successful()) {
            Log::warning('Langfuse dataset fetch failed', [
                'status' => $response->status(),
                'datasetName' => $datasetName,
            ]);

            return null;
        }

        /** @var array<string, mixed> $data */
        $data = $response->json() ?? [];

        return DatasetResponse::fromArray($data);
    }

    private function doList(?int $page, ?int $limit): ?DatasetListResponse
    {
        $query = array_filter([
            'page' => $page,
            'limit' => $limit,
        ], fn (mixed $value): bool => $value !== null);

        $response = Http::withHeaders([
            'Authorization' => $this->config->authHeader(),
            'Content-Type' => 'application/json',
        ])
            ->timeout($this->config->requestTimeout)
            ->get($this->config->datasetsUrl(), $query);

        if (! $response->successful()) {
            Log::warning('Langfuse dataset list failed', [
                'status' => $response->status(),
            ]);

            return null;
        }

        /** @var array<string, mixed> $data */
        $data = $response->json() ?? [];

        return DatasetListResponse::fromArray($data);
    }

    private function doCreate(CreateDatasetBody $body): ?DatasetResponse
    {
        $response = Http::withHeaders([
            'Authorization' => $this->config->authHeader(),
            'Content-Type' => 'application/json',
        ])
            ->timeout($this->config->requestTimeout)
            ->post($this->config->datasetsUrl(), $body->toArray());

        if (! $response->successful()) {
            Log::warning('Langfuse dataset create failed', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return null;
        }

        /** @var array<string, mixed> $data */
        $data = $response->json() ?? [];

        return DatasetResponse::fromArray($data);
    }
}
