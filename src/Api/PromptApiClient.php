<?php

declare(strict_types=1);

namespace Initred\Langfuse\Api;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Initred\Langfuse\Config\LangfuseConfig;
use Initred\Langfuse\Contracts\PromptApiClientInterface;
use Initred\Langfuse\Contracts\PromptInterface;
use Initred\Langfuse\Dto\CreatePromptBody;
use Initred\Langfuse\Dto\PromptFactory;
use Initred\Langfuse\Dto\PromptListResponse;
use Throwable;

class PromptApiClient implements PromptApiClientInterface
{
    public function __construct(
        private readonly LangfuseConfig $config,
    ) {}

    public function get(string $name, ?int $version = null, ?string $label = null): ?array
    {
        try {
            return $this->doGet($name, $version, $label);
        } catch (Throwable $throwable) {
            Log::warning('Langfuse prompt fetch error', ['message' => $throwable->getMessage()]);

            return null;
        }
    }

    public function create(CreatePromptBody $body): ?PromptInterface
    {
        try {
            return $this->doCreate($body);
        } catch (Throwable $throwable) {
            Log::warning('Langfuse prompt create error', ['message' => $throwable->getMessage()]);

            return null;
        }
    }

    public function list(?string $name = null, ?string $label = null, ?int $page = null, ?int $limit = null): ?PromptListResponse
    {
        try {
            return $this->doList($name, $label, $page, $limit);
        } catch (Throwable $throwable) {
            Log::warning('Langfuse prompt list error', ['message' => $throwable->getMessage()]);

            return null;
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    private function doGet(string $name, ?int $version, ?string $label): ?array
    {
        $query = array_filter([
            'version' => $version,
            'label' => $label,
        ], fn (mixed $value): bool => $value !== null);

        $response = Http::withHeaders([
            'Authorization' => $this->config->authHeader(),
            'Content-Type' => 'application/json',
        ])
            ->timeout($this->config->requestTimeout)
            ->get($this->config->promptsUrl($name), $query);

        if (! $response->successful()) {
            Log::warning('Langfuse prompt fetch failed', [
                'status' => $response->status(),
                'name' => $name,
            ]);

            return null;
        }

        /** @var array<string, mixed>|null */
        return $response->json();
    }

    private function doCreate(CreatePromptBody $body): ?PromptInterface
    {
        $response = Http::withHeaders([
            'Authorization' => $this->config->authHeader(),
            'Content-Type' => 'application/json',
        ])
            ->timeout($this->config->requestTimeout)
            ->post($this->config->promptsUrl(), $body->toArray());

        if (! $response->successful()) {
            Log::warning('Langfuse prompt create failed', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return null;
        }

        /** @var array<string, mixed> $data */
        $data = $response->json() ?? [];

        return PromptFactory::fromApiResponse($data);
    }

    private function doList(?string $name, ?string $label, ?int $page, ?int $limit): ?PromptListResponse
    {
        $query = array_filter([
            'name' => $name,
            'label' => $label,
            'page' => $page,
            'limit' => $limit,
        ], fn (mixed $value): bool => $value !== null);

        $response = Http::withHeaders([
            'Authorization' => $this->config->authHeader(),
            'Content-Type' => 'application/json',
        ])
            ->timeout($this->config->requestTimeout)
            ->get($this->config->promptsUrl(), $query);

        if (! $response->successful()) {
            Log::warning('Langfuse prompt list failed', [
                'status' => $response->status(),
            ]);

            return null;
        }

        /** @var array<string, mixed> $data */
        $data = $response->json() ?? [];

        return PromptListResponse::fromArray($data);
    }
}
