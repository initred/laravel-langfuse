<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use Initred\Langfuse\Config\LangfuseConfig;
use Initred\Langfuse\Contracts\EventBatcherInterface;
use Initred\Langfuse\Contracts\IngestionApiClientInterface;
use Initred\Langfuse\Contracts\LangfuseClientInterface;
use Initred\Langfuse\Contracts\PromptApiClientInterface;
use Initred\Langfuse\Contracts\PromptCacheInterface;
use Initred\Langfuse\Contracts\PromptInterface;
use Initred\Langfuse\LangfuseFacade;
use Initred\Langfuse\Prompt\PromptManager;

beforeEach(function () {
    config([
        'langfuse.public_key' => 'pk-test',
        'langfuse.secret_key' => 'sk-test',
        'langfuse.base_url' => 'https://test.langfuse.com',
        'langfuse.enabled' => true,
        'langfuse.flush_at' => 100,
    ]);

    $this->app->forgetInstance(LangfuseConfig::class);
    $this->app->forgetInstance(EventBatcherInterface::class);
    $this->app->forgetInstance(IngestionApiClientInterface::class);
    $this->app->forgetInstance(LangfuseClientInterface::class);
    $this->app->forgetInstance(PromptApiClientInterface::class);
    $this->app->forgetInstance(PromptCacheInterface::class);
    $this->app->forgetInstance(PromptManager::class);
});

it('fetches prompt via facade', function () {
    Http::fake([
        'test.langfuse.com/api/public/v2/prompts/*' => Http::response([
            'name' => 'movie-critic',
            'version' => 1,
            'type' => 'text',
            'prompt' => 'Review {{movie}}',
            'config' => ['model' => 'gpt-4'],
            'labels' => ['production'],
        ]),
    ]);

    $prompt = LangfuseFacade::prompt('movie-critic');

    expect($prompt)->toBeInstanceOf(PromptInterface::class)
        ->and($prompt->getName())->toBe('movie-critic')
        ->and($prompt->compile(['movie' => 'Dune 2']))->toBe('Review Dune 2');
});

it('fetches prompt with version via facade', function () {
    Http::fake([
        'test.langfuse.com/api/public/v2/prompts/*' => Http::response([
            'name' => 'test',
            'version' => 3,
            'type' => 'text',
            'prompt' => 'v3 prompt',
        ]),
    ]);

    $prompt = LangfuseFacade::prompt('test', version: 3);

    expect($prompt->getVersion())->toBe(3);
});

it('uses fallback when api fails via facade', function () {
    Http::fake([
        'test.langfuse.com/api/public/v2/prompts/*' => Http::response('Error', 500),
    ]);

    $prompt = LangfuseFacade::prompt('test', fallback: 'Fallback {{var}}');

    expect($prompt->isFallback())->toBeTrue()
        ->and($prompt->compile(['var' => 'value']))->toBe('Fallback value');
});
