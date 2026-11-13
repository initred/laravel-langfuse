<?php

declare(strict_types=1);

namespace Initred\Langfuse;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Initred\Langfuse\Api\DatasetApiClient;
use Initred\Langfuse\Api\DatasetItemApiClient;
use Initred\Langfuse\Api\ExperimentApiClient;
use Initred\Langfuse\Api\IngestionApiClient;
use Initred\Langfuse\Api\MetricsApiClient;
use Initred\Langfuse\Api\ObservationApiClient;
use Initred\Langfuse\Api\OtelTraceApiClient;
use Initred\Langfuse\Api\PromptApiClient;
use Initred\Langfuse\Api\ScoreApiClient;
use Initred\Langfuse\Batch\EventBatcher;
use Initred\Langfuse\Batch\NullEventBatcher;
use Initred\Langfuse\Batch\QueuedEventBatcher;
use Initred\Langfuse\Cache\PromptCache;
use Initred\Langfuse\Config\LangfuseConfig;
use Initred\Langfuse\Contracts\DatasetApiClientInterface;
use Initred\Langfuse\Contracts\DatasetItemApiClientInterface;
use Initred\Langfuse\Contracts\EventBatcherInterface;
use Initred\Langfuse\Contracts\ExperimentApiClientInterface;
use Initred\Langfuse\Contracts\IngestionApiClientInterface;
use Initred\Langfuse\Contracts\LangfuseClientInterface;
use Initred\Langfuse\Contracts\MetricsApiClientInterface;
use Initred\Langfuse\Contracts\ObservationApiClientInterface;
use Initred\Langfuse\Contracts\OtelTraceApiClientInterface;
use Initred\Langfuse\Contracts\PromptApiClientInterface;
use Initred\Langfuse\Contracts\PromptCacheInterface;
use Initred\Langfuse\Contracts\ScoreApiClientInterface;
use Initred\Langfuse\Contracts\TraceContextResolverInterface;
use Initred\Langfuse\Objects\OpenObservationRegistry;
use Initred\Langfuse\Prompt\CurrentPromptRegistry;
use Initred\Langfuse\Prompt\PromptManager;
use Initred\Langfuse\Tracing\AuthTraceContextResolver;
use Laravel\Ai\Events\PromptingAgent;
use NeuronAI\Observability\ObservabilityEvent;

class LangfuseServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/langfuse.php', 'langfuse');

        $this->registerConfig();
        $this->registerIngestion();
        $this->registerPrompts();

        $this->app->singleton(TraceContextResolverInterface::class, AuthTraceContextResolver::class);
        $this->app->scoped(LangfuseClientInterface::class, LangfuseClient::class);
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../config/langfuse.php' => $this->app->configPath('langfuse.php'),
        ], 'langfuse-config');

        // v4 exports an observation only when it ends, so the terminate hook
        // ends whatever is still open before the final flush.
        $this->app->terminating(function () {
            $langfuse = $this->app->make(LangfuseClientInterface::class);
            $langfuse->shutdown();
        });

        $this->registerLaravelAiIntegration();
        $this->registerNeuronAiIntegration();
    }

    private function registerConfig(): void
    {
        $this->app->singleton(LangfuseConfig::class, function () {
            $repository = $this->app->make(Repository::class);

            /** @var array<string, mixed> $config */
            $config = $repository->get('langfuse', []);

            return LangfuseConfig::fromArray($config);
        });
    }

    private function registerIngestion(): void
    {
        $this->app->singleton(IngestionApiClientInterface::class, IngestionApiClient::class);
        $this->app->singleton(OtelTraceApiClientInterface::class, OtelTraceApiClient::class);
        $this->app->singleton(ScoreApiClientInterface::class, ScoreApiClient::class);
        $this->app->singleton(ObservationApiClientInterface::class, ObservationApiClient::class);
        $this->app->singleton(MetricsApiClientInterface::class, MetricsApiClient::class);
        $this->app->singleton(DatasetApiClientInterface::class, DatasetApiClient::class);
        $this->app->singleton(DatasetItemApiClientInterface::class, DatasetItemApiClient::class);
        $this->app->singleton(ExperimentApiClientInterface::class, ExperimentApiClient::class);

        $this->app->scoped(OpenObservationRegistry::class);

        $this->app->scoped(EventBatcherInterface::class, function () {
            $config = $this->app->make(LangfuseConfig::class);

            if (! $config->enabled) {
                return new NullEventBatcher;
            }

            if ($config->queue !== null) {
                return $this->app->make(QueuedEventBatcher::class);
            }

            return $this->app->make(EventBatcher::class);
        });
    }

    private function registerPrompts(): void
    {
        $this->app->singleton(PromptApiClientInterface::class, PromptApiClient::class);
        $this->app->singleton(PromptCacheInterface::class, PromptCache::class);

        $this->app->scoped(CurrentPromptRegistry::class);
        $this->app->singleton(PromptManager::class, function () {
            $config = $this->app->make(LangfuseConfig::class);

            return new PromptManager(
                apiClient: $this->app->make(PromptApiClientInterface::class),
                cache: $this->app->make(PromptCacheInterface::class),
                cacheTtl: $config->promptCacheTtl,
            );
        });
    }

    private function registerLaravelAiIntegration(): void
    {
        if (! class_exists(PromptingAgent::class)) {
            return;
        }

        $config = $this->app->make(LangfuseConfig::class);

        if (! $config->laravelAiEnabled) {
            return;
        }

        $this->app->scoped(LaravelAi\LaravelAiSubscriber::class);

        Event::subscribe(LaravelAi\LaravelAiSubscriber::class);
    }

    private function registerNeuronAiIntegration(): void
    {
        if (! class_exists(ObservabilityEvent::class)) {
            return;
        }

        $config = $this->app->make(LangfuseConfig::class);

        if (! $config->neuronAiEnabled) {
            return;
        }

        $this->app->scoped(NeuronAi\NeuronAiObserver::class);
    }
}
