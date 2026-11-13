<?php

declare(strict_types=1);

namespace Tests\Fixtures\LaravelAi;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class TestTool implements Tool
{
    public function description(): Stringable|string
    {
        return 'A test tool.';
    }

    public function handle(Request $request): Stringable|string
    {
        return 'result';
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [];
    }
}
