<?php

declare(strict_types=1);

use Initred\Langfuse\Config\LangfuseConfig;
use Initred\Langfuse\Contracts\EventBatcherInterface;
use Initred\Langfuse\Contracts\LangfuseClientInterface;
use Initred\Langfuse\Enums\ObservationType;
use Initred\Langfuse\LangfuseServiceProvider;
use Initred\Langfuse\LaravelAi\LaravelAiSubscriber;
use Initred\Langfuse\Prompt\PromptManager;
use Initred\Langfuse\Testing\RecordingEventBatcher;
use Initred\Langfuse\Tracing\CompletedObservation;
use Laravel\Ai\Contracts\Providers\TextProvider;
use Laravel\Ai\Events\AgentPrompted;
use Laravel\Ai\Events\InvokingTool;
use Laravel\Ai\Events\PromptingAgent;
use Laravel\Ai\Events\ToolInvoked;
use Laravel\Ai\Prompts\AgentPrompt;
use Laravel\Ai\Responses\AgentResponse;
use Laravel\Ai\Responses\Data\Meta;
use Laravel\Ai\Responses\Data\TextUsage;
use Tests\Fixtures\LaravelAi\TestAgent;
use Tests\Fixtures\LaravelAi\TestTool;

it('does not register subscriber when disabled', function () {
    config(['langfuse.laravel_ai_enabled' => false]);

    expect($this->app->bound(LaravelAiSubscriber::class))->toBeFalse();
});

it('registers subscriber when enabled', function () {
    config(['langfuse.laravel_ai_enabled' => true]);

    // Re-bootstrap with laravel_ai enabled
    $this->app->forgetInstance(LangfuseConfig::class);
    $this->app->forgetInstance(LangfuseClientInterface::class);
    $this->app->forgetInstance(EventBatcherInterface::class);
    $this->app->forgetInstance(PromptManager::class);

    $provider = new LangfuseServiceProvider($this->app);
    $provider->register();
    $provider->boot();

    expect($this->app->bound(LaravelAiSubscriber::class))->toBeTrue();
});

it('dispatches events through subscriber when enabled', function () {
    config([
        'langfuse.laravel_ai_enabled' => true,
        'langfuse.public_key' => 'pk-test',
        'langfuse.secret_key' => 'sk-test',
    ]);

    // Re-bootstrap
    $this->app->forgetInstance(LangfuseConfig::class);
    $this->app->forgetInstance(LangfuseClientInterface::class);
    $this->app->forgetInstance(EventBatcherInterface::class);
    $this->app->forgetInstance(PromptManager::class);

    $provider = new LangfuseServiceProvider($this->app);
    $provider->register();

    // Bind recording batcher AFTER register() so it overrides the scoped binding
    $batcher = new RecordingEventBatcher;
    $this->app->instance(EventBatcherInterface::class, $batcher);

    $provider->boot();

    // Create test event objects
    $agent = new TestAgent;
    $textProvider = Mockery::mock(TextProvider::class);
    $prompt = new AgentPrompt(
        agent: $agent,
        prompt: 'Hello AI',
        attachments: [],
        provider: $textProvider,
        model: 'gpt-4',
    );

    // Dispatch events
    event(new PromptingAgent(
        invocationId: 'inv-1',
        prompt: $prompt,
    ));

    event(new AgentPrompted(
        invocationId: 'inv-1',
        prompt: $prompt,
        response: new AgentResponse(
            invocationId: 'inv-1',
            text: 'Hello human',
            usage: new TextUsage(inputTokens: 10, outputTokens: 20),
            meta: new Meta(provider: 'openai', model: 'gpt-4'),
        ),
    ));

    // The owned trace and its generation are both exported once, when they end.
    $names = array_map(
        fn (CompletedObservation $o): string => $o->name(),
        $batcher->observations(),
    );

    expect($names)->toHaveCount(2)
        ->and($names[0])->toBe('gpt-4')
        ->and($names[1])->toStartWith('laravel-ai-')
        ->and($batcher->observationsOfType(ObservationType::Generation))->toHaveCount(1);
});

it('dispatches tool events through subscriber', function () {
    config([
        'langfuse.laravel_ai_enabled' => true,
        'langfuse.public_key' => 'pk-test',
        'langfuse.secret_key' => 'sk-test',
    ]);

    // Re-bootstrap
    $this->app->forgetInstance(LangfuseConfig::class);
    $this->app->forgetInstance(LangfuseClientInterface::class);
    $this->app->forgetInstance(EventBatcherInterface::class);
    $this->app->forgetInstance(PromptManager::class);

    $provider = new LangfuseServiceProvider($this->app);
    $provider->register();

    // Bind recording batcher AFTER register()
    $batcher = new RecordingEventBatcher;
    $this->app->instance(EventBatcherInterface::class, $batcher);

    $provider->boot();

    $agent = new TestAgent;
    $textProvider = Mockery::mock(TextProvider::class);
    $tool = new TestTool;
    $prompt = new AgentPrompt(
        agent: $agent,
        prompt: 'Search for something',
        attachments: [],
        provider: $textProvider,
        model: 'gpt-4',
    );

    // Agent prompt first (creates trace)
    event(new PromptingAgent(invocationId: 'inv-1', prompt: $prompt));

    // Tool invocation
    event(new InvokingTool(
        invocationId: 'inv-1',
        toolInvocationId: 'tool-1',
        agent: $agent,
        tool: $tool,
        arguments: ['query' => 'test'],
    ));

    event(new ToolInvoked(
        invocationId: 'inv-1',
        toolInvocationId: 'tool-1',
        agent: $agent,
        tool: $tool,
        arguments: ['query' => 'test'],
        result: 'Search results here',
        time: 0.0,
    ));

    $spans = $batcher->observationsOfType(ObservationType::Tool);

    expect($spans)->toHaveCount(1)
        ->and($spans[0]->name())->toStartWith('tool-')
        ->and($spans[0]->body->output)->toBe('Search results here');
});
