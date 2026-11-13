<?php

declare(strict_types=1);

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Initred\Langfuse\Api\IngestionApiClient;
use Initred\Langfuse\Batch\EventBatcher;
use Initred\Langfuse\Batch\ScoreBatchFactory;
use Initred\Langfuse\Config\LangfuseConfig;
use Initred\Langfuse\Contracts\EventBatcherInterface;
use Initred\Langfuse\Contracts\IngestionApiClientInterface;
use Initred\Langfuse\Contracts\LangfuseClientInterface;
use Initred\Langfuse\Contracts\OtelTraceApiClientInterface;
use Initred\Langfuse\Contracts\ScoreApiClientInterface;
use Initred\Langfuse\Dto\IngestionBatch;
use Initred\Langfuse\Dto\SpanBody;
use Initred\Langfuse\Dto\TraceBody;
use Initred\Langfuse\LangfuseFacade;
use Initred\Langfuse\Objects\OpenObservationRegistry;
use Initred\Langfuse\Otlp\OtlpRequestFactory;
use Initred\Langfuse\Tracing\CompletedObservation;
use Initred\Langfuse\Tracing\TraceContext;

beforeEach(function () {
    config([
        'langfuse.public_key' => 'pk-test',
        'langfuse.secret_key' => 'sk-test',
        'langfuse.enabled' => true,
        'langfuse.flush_at' => 100,
    ]);

    $this->app->forgetInstance(LangfuseConfig::class);
    $this->app->forgetInstance(EventBatcherInterface::class);
    $this->app->forgetInstance(IngestionApiClientInterface::class);
    $this->app->forgetInstance(OtelTraceApiClientInterface::class);
    $this->app->forgetInstance(ScoreApiClientInterface::class);
    $this->app->forgetInstance(LangfuseClientInterface::class);
    $this->app->forgetInstance(OpenObservationRegistry::class);
});

it('handles connection timeout without throwing', function () {
    Http::fake(function () {
        throw new ConnectionException('Connection timed out');
    });

    Log::shouldReceive('warning')->atLeast()->once();

    LangfuseFacade::trace(new TraceBody(id: 'trace-timeout'))->end();
    LangfuseFacade::flush();

    // If we get here, no exception was thrown
    expect(true)->toBeTrue();
});

it('handles DNS resolution failure without throwing', function () {
    Http::fake(function () {
        throw new ConnectionException('DNS resolution failed');
    });

    Log::shouldReceive('warning')->atLeast()->once();

    LangfuseFacade::trace(new TraceBody(id: 'trace-dns'))->end();
    LangfuseFacade::flush();

    expect(true)->toBeTrue();
});

it('handles 500 server error without throwing', function () {
    Http::fake(['*' => Http::response('Internal Server Error', 500)]);

    Log::shouldReceive('warning')->atLeast()->once();

    LangfuseFacade::trace(new TraceBody(id: 'trace-500'))->end();
    LangfuseFacade::flush();

    expect(true)->toBeTrue();
});

it('handles 401 unauthorized without throwing', function () {
    Http::fake(['*' => Http::response('Unauthorized', 401)]);

    Log::shouldReceive('warning')->atLeast()->once();

    LangfuseFacade::trace(new TraceBody(id: 'trace-401'))->end();
    LangfuseFacade::flush();

    expect(true)->toBeTrue();
});

it('handles invalid JSON response without throwing', function () {
    Http::fake(['*' => Http::response('not json at all', 200)]);

    LangfuseFacade::trace(new TraceBody(id: 'trace-invalid-json'))->end();
    LangfuseFacade::flush();

    expect(true)->toBeTrue();
});

it('never throws out of shutdown', function () {
    Http::fake(function () {
        throw new ConnectionException('Connection timed out');
    });

    Log::shouldReceive('warning')->atLeast()->once();

    $trace = LangfuseFacade::trace(new TraceBody(id: 'trace-shutdown'));
    $trace->span(new SpanBody(name: 'unfinished'));

    LangfuseFacade::shutdown();

    expect(true)->toBeTrue();
});

it('resets batcher queue after failed flush', function () {
    $config = new LangfuseConfig(publicKey: 'pk', secretKey: 'sk', flushAt: 100);

    $otelClient = Mockery::mock(OtelTraceApiClientInterface::class);
    $otelClient->shouldReceive('export')->once()->andThrow(new RuntimeException('Network error'));

    Log::shouldReceive('warning')->atLeast()->once();

    $batcher = new EventBatcher(
        otelClient: $otelClient,
        scoreApiClient: Mockery::mock(ScoreApiClientInterface::class),
        config: $config,
        requestFactory: new OtlpRequestFactory($config),
        scoreBatchFactory: new ScoreBatchFactory($config),
    );

    $context = new TraceContext(new TraceBody(id: 'trace-1'));
    $body = (new SpanBody(name: 'work'))->withContext($context->traceId(), $context->rootObservationId());

    $batcher->enqueue(CompletedObservation::span($body->completed('2024-01-01T00:00:01Z'), $context));

    expect($batcher->count())->toBe(1);

    $batcher->flush();

    expect($batcher->count())->toBe(0);
});

it('handles empty config gracefully', function () {
    $config = LangfuseConfig::fromArray([]);

    expect($config->publicKey)->toBe('')
        ->and($config->secretKey)->toBe('')
        ->and($config->enabled)->toBeTrue();
});

it('api client returns null on send failure without affecting subsequent calls', function () {
    Http::fake([
        '*' => Http::sequence()
            ->push('Server Error', 500)
            ->push(['successes' => [['id' => 'evt-1', 'status' => 201]], 'errors' => []]),
    ]);

    Log::shouldReceive('warning')->once();

    $client = new IngestionApiClient(new LangfuseConfig(publicKey: 'pk', secretKey: 'sk'));
    $batch = new IngestionBatch(batch: []);

    expect($client->send($batch))->toBeNull();

    $result = $client->send($batch);

    expect($result)->not->toBeNull()
        ->and($result->successes)->toHaveCount(1);
});
