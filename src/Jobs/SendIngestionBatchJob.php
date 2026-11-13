<?php

declare(strict_types=1);

namespace Initred\Langfuse\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use Initred\Langfuse\Contracts\IngestionApiClientInterface;
use Throwable;

/**
 * Sends the score batch. Observations moved to SendOtelBatchJob in v4.
 */
class SendIngestionBatchJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    public int $tries = 3;

    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public readonly array $payload,
    ) {}

    /**
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [10, 60, 300];
    }

    public function handle(IngestionApiClientInterface $apiClient): void
    {
        $apiClient->sendRaw($this->payload);
    }

    public function failed(Throwable $exception): void
    {
        $batch = $this->payload['batch'] ?? [];

        Log::warning('Langfuse score batch failed permanently', [
            'message' => $exception->getMessage(),
            'scores' => is_array($batch) ? count($batch) : 0,
        ]);
    }
}
