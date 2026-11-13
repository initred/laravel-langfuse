<?php

declare(strict_types=1);

namespace Initred\Langfuse\Batch;

use Initred\Langfuse\Contracts\EventBatcherInterface;
use Initred\Langfuse\Dto\ScoreBody;
use Initred\Langfuse\Tracing\CompletedObservation;

class NullEventBatcher implements EventBatcherInterface
{
    public function enqueue(CompletedObservation $observation): void {}

    public function enqueueScore(ScoreBody $score, ?string $timestamp = null): void {}

    public function flush(): void {}

    public function count(): int
    {
        return 0;
    }
}
