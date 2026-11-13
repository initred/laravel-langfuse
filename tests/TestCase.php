<?php

declare(strict_types=1);

namespace Tests;

use Initred\Langfuse\LangfuseServiceProvider;
use Orchestra\Testbench\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * @return array<int, class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [
            LangfuseServiceProvider::class,
        ];
    }
}
