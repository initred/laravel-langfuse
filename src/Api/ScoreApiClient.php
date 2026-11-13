<?php

declare(strict_types=1);

namespace Initred\Langfuse\Api;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Initred\Langfuse\Config\LangfuseConfig;
use Initred\Langfuse\Contracts\IngestionApiClientInterface;
use Initred\Langfuse\Contracts\ScoreApiClientInterface;
use Initred\Langfuse\Dto\IngestionBatch;
use Initred\Langfuse\Dto\IngestionResponse;
use Initred\Langfuse\Dto\ScoreListResponse;
use Initred\Langfuse\Dto\ScoreQuery;
use Initred\Langfuse\Dto\ScoreResponse;
use Throwable;

/**
 * Owns every score call. Reads use v3, writes still go through the ingestion
 * endpoint as `score-create` events, which v4 keeps supporting; moving them to
 * POST /api/public/scores later is a change to this file alone.
 */
class ScoreApiClient implements ScoreApiClientInterface
{
    /** Everything the get-by-id lookup can return about a single score. */
    private const string ALL_FIELDS = 'details,subject,annotation';

    public function __construct(
        private readonly LangfuseConfig $config,
        private readonly IngestionApiClientInterface $ingestionApiClient,
    ) {}

    public function ingest(IngestionBatch $batch): ?IngestionResponse
    {
        return $this->ingestionApiClient->send($batch);
    }

    public function get(string $scoreId): ?ScoreResponse
    {
        try {
            return $this->doGet($scoreId);
        } catch (Throwable $throwable) {
            Log::warning('Langfuse score fetch error', ['message' => $throwable->getMessage()]);

            return null;
        }
    }

    public function getMany(?ScoreQuery $query = null): ?ScoreListResponse
    {
        try {
            return $this->doGetMany($query);
        } catch (Throwable $throwable) {
            Log::warning('Langfuse score list error', ['message' => $throwable->getMessage()]);

            return null;
        }
    }

    public function delete(string $scoreId): bool
    {
        try {
            return $this->doDelete($scoreId);
        } catch (Throwable $throwable) {
            Log::warning('Langfuse score delete error', ['message' => $throwable->getMessage()]);

            return false;
        }
    }

    /**
     * v3 has no get-by-id route: a single score is an `id` filter on the list.
     */
    private function doGet(string $scoreId): ?ScoreResponse
    {
        $response = $this->request([
            'id' => $scoreId,
            'fields' => self::ALL_FIELDS,
            'limit' => 1,
        ]);

        if ($response === null) {
            Log::warning('Langfuse score fetch failed', ['scoreId' => $scoreId]);

            return null;
        }

        return $response->data[0] ?? null;
    }

    private function doGetMany(?ScoreQuery $query): ?ScoreListResponse
    {
        $response = $this->request($query?->toQuery() ?? []);

        if ($response === null) {
            Log::warning('Langfuse score list failed');
        }

        return $response;
    }

    /**
     * @param  array<string, string|int|float>  $parameters
     */
    private function request(array $parameters): ?ScoreListResponse
    {
        $response = Http::withHeaders([
            'Authorization' => $this->config->authHeader(),
            'Content-Type' => 'application/json',
        ])
            ->timeout($this->config->requestTimeout)
            ->get($this->config->scoresV3Url(), $parameters);

        if (! $response->successful()) {
            Log::warning('Langfuse score request failed', ['status' => $response->status()]);

            return null;
        }

        /** @var array<string, mixed> $data */
        $data = $response->json() ?? [];

        return ScoreListResponse::fromArray($data);
    }

    private function doDelete(string $scoreId): bool
    {
        // Intentional: reads use the v3 scores endpoint, but the spec only
        // exposes DELETE on the unversioned endpoint (scoresUrl, not
        // scoresV3Url). Do not "consolidate" this - there is no v3 DELETE.
        $response = Http::withHeaders([
            'Authorization' => $this->config->authHeader(),
        ])
            ->timeout($this->config->requestTimeout)
            ->delete($this->config->scoresUrl($scoreId));

        if (! $response->successful()) {
            Log::warning('Langfuse score delete failed', [
                'status' => $response->status(),
                'scoreId' => $scoreId,
            ]);

            return false;
        }

        return true;
    }
}
