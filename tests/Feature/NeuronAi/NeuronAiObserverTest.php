<?php

declare(strict_types=1);

use Initred\Langfuse\Config\LangfuseConfig;
use Initred\Langfuse\Contracts\EventBatcherInterface;
use Initred\Langfuse\Contracts\LangfuseClientInterface;
use Initred\Langfuse\Enums\ObservationType;
use Initred\Langfuse\LangfuseServiceProvider;
use Initred\Langfuse\NeuronAi\NeuronAiObserver;
use Initred\Langfuse\Prompt\PromptManager;
use Initred\Langfuse\Testing\RecordingEventBatcher;
use Initred\Langfuse\Tracing\CompletedObservation;
use NeuronAI\Agent\Agent;
use NeuronAI\Chat\Messages\AssistantMessage;
use NeuronAI\Chat\Messages\Message;
use NeuronAI\Chat\Messages\ToolCallMessage;
use NeuronAI\Chat\Messages\Usage;
use NeuronAI\Chat\Messages\UserMessage;
use NeuronAI\Observability\ObservabilityEvent;
use NeuronAI\Providers\AIProviderInterface;
use NeuronAI\Providers\ProviderResponse;
use NeuronAI\Testing\FakeAIProvider;
use NeuronAI\Tools\Tool;
use NeuronAI\Tools\ToolCall;

it('does not register observer when disabled', function () {
    config(['langfuse.neuron_ai_enabled' => false]);

    expect($this->app->bound(NeuronAiObserver::class))->toBeFalse();
});

it('registers observer when enabled', function () {
    config(['langfuse.neuron_ai_enabled' => true]);

    // Re-bootstrap with neuron_ai enabled
    $this->app->forgetInstance(LangfuseConfig::class);
    $this->app->forgetInstance(LangfuseClientInterface::class);
    $this->app->forgetInstance(EventBatcherInterface::class);
    $this->app->forgetInstance(PromptManager::class);

    $provider = new LangfuseServiceProvider($this->app);
    $provider->register();
    $provider->boot();

    expect($this->app->bound(NeuronAiObserver::class))->toBeTrue();
});

class NeuronSearchTool extends Tool
{
    protected string $name = 'search';

    protected ?string $description = 'Search the docs';

    public function __invoke(): string
    {
        return 'Search results';
    }
}

class NeuronFeatureAgent extends Agent
{
    public static ?AIProviderInterface $fakeProvider = null;

    protected function provider(): AIProviderInterface
    {
        return self::$fakeProvider ?? new FakeAIProvider;
    }

    protected function tools(): array
    {
        return [new NeuronSearchTool];
    }
}

function bootNeuronLangfuse(): RecordingEventBatcher
{
    config([
        'langfuse.neuron_ai_enabled' => true,
        'langfuse.public_key' => 'pk-test',
        'langfuse.secret_key' => 'sk-test',
    ]);

    $app = app();
    $app->forgetInstance(LangfuseConfig::class);
    $app->forgetInstance(LangfuseClientInterface::class);
    $app->forgetInstance(EventBatcherInterface::class);
    $app->forgetInstance(PromptManager::class);

    $provider = new LangfuseServiceProvider($app);
    $provider->register();

    // Bind recording batcher AFTER register() so it overrides the scoped binding
    $batcher = new RecordingEventBatcher;
    $app->instance(EventBatcherInterface::class, $batcher);

    $provider->boot();

    return $batcher;
}

/**
 * @return list<string>
 */
function neuronObservationNames(RecordingEventBatcher $batcher): array
{
    return array_map(fn (CompletedObservation $o): string => $o->name(), $batcher->observations());
}

it('traces a real agent chat end to end', function () {
    $batcher = bootNeuronLangfuse();

    $response = new AssistantMessage('Hello human');
    $response->setUsage(new Usage(inputTokens: 10, outputTokens: 20));
    NeuronFeatureAgent::$fakeProvider = new FakeAIProvider($response);

    NeuronFeatureAgent::make()
        ->setThreadId('test')
        ->subscribe(ObservabilityEvent::class, NeuronAiObserver::make())
        ->chat(new UserMessage('Hello AI'));

    $generations = $batcher->observationsOfType(ObservationType::Generation);

    expect(neuronObservationNames($batcher))->toBe(['inference', 'neuron-ai-NeuronFeatureAgent'])
        ->and($generations)->toHaveCount(1)
        ->and($generations[0]->body->input)->toBe('Hello AI')
        ->and($generations[0]->body->output)->toBe('Hello human')
        ->and($generations[0]->body->model)->toBe('fake')
        ->and($generations[0]->body->usage?->input)->toBe(10)
        ->and($generations[0]->body->usage?->output)->toBe(20);
});

it('traces the tool calls of a real agent', function () {
    $batcher = bootNeuronLangfuse();

    NeuronFeatureAgent::$fakeProvider = new FakeAIProvider(
        new ToolCallMessage(null, [new ToolCall(name: 'search', callId: 'call_1', inputs: [])]),
        new AssistantMessage('Found it'),
    );

    NeuronFeatureAgent::make()
        ->setThreadId('test')
        ->subscribe(ObservabilityEvent::class, NeuronAiObserver::make())
        ->chat(new UserMessage('Search it'));

    $tools = $batcher->observationsOfType(ObservationType::Tool);

    expect($tools)->toHaveCount(1)
        ->and($tools[0]->name())->toBe('tool-search')
        ->and($tools[0]->body->output)->toBe('Search results')
        ->and($batcher->observationsOfType(ObservationType::Generation))->toHaveCount(2)
        ->and(neuronObservationNames($batcher))->toContain('neuron-ai-NeuronFeatureAgent');
});

it('records a failed agent run on its trace', function () {
    $batcher = bootNeuronLangfuse();

    NeuronFeatureAgent::$fakeProvider = new class extends FakeAIProvider
    {
        public function chat(Message ...$messages): ProviderResponse
        {
            throw new RuntimeException('Provider is down');
        }
    };

    expect(fn () => NeuronFeatureAgent::make()
        ->setThreadId('test')
        ->subscribe(ObservabilityEvent::class, NeuronAiObserver::make())
        ->chat(new UserMessage('Hello')))->toThrow(RuntimeException::class);

    $traces = array_values(array_filter(
        $batcher->observations(),
        fn (CompletedObservation $o): bool => $o->name() === 'neuron-ai-NeuronFeatureAgent',
    ));

    expect($traces)->toHaveCount(1)
        ->and($traces[0]->body->metadata['error'] ?? null)->toContain('Provider is down')
        ->and(neuronObservationNames($batcher))->toHaveCount(1);
});

afterEach(function () {
    NeuronFeatureAgent::$fakeProvider = null;
});
