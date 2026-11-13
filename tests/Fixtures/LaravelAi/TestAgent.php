<?php

declare(strict_types=1);

namespace Tests\Fixtures\LaravelAi;

use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Promptable;
use Stringable;

class TestAgent implements Agent
{
    use Promptable;

    public function instructions(): Stringable|string
    {
        return 'You are a test agent.';
    }
}
