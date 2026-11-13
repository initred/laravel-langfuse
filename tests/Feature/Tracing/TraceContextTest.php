<?php

declare(strict_types=1);

use Illuminate\Auth\GenericUser;
use Illuminate\Support\Facades\Log;
use Initred\Langfuse\Batch\NullEventBatcher;
use Initred\Langfuse\Config\LangfuseConfig;
use Initred\Langfuse\Contracts\EventBatcherInterface;
use Initred\Langfuse\Contracts\LangfuseClientInterface;
use Initred\Langfuse\Contracts\TraceContextResolverInterface;
use Initred\Langfuse\Dto\IdGenerator;
use Initred\Langfuse\Dto\TraceBody;
use Initred\Langfuse\LangfuseFacade;
use Initred\Langfuse\Objects\LangfuseTrace;
use Initred\Langfuse\Tracing\AuthTraceContextResolver;

function recordTraces(): void
{
    config(['langfuse.public_key' => 'pk', 'langfuse.secret_key' => 'sk']);

    app()->instance(EventBatcherInterface::class, new NullEventBatcher);
}

/**
 * @return array<string, mixed>
 */
function traceBodyOf(LangfuseTrace $trace): array
{
    return $trace->getBody()->toArray();
}

it('binds the auth resolver as the default', function () {
    expect($this->app->make(TraceContextResolverInterface::class))->toBeInstanceOf(AuthTraceContextResolver::class)
        ->and($this->app->make(TraceContextResolverInterface::class))->toBe($this->app->make(TraceContextResolverInterface::class));
});

it('tags traces with the authenticated user', function () {
    recordTraces();
    $this->actingAs(new GenericUser(['id' => 42]));

    expect(traceBodyOf(LangfuseFacade::trace(new TraceBody(id: 'trace-1')))['userId'])->toBe('42');
});

it('leaves guests without a userId', function () {
    recordTraces();

    expect(traceBodyOf(LangfuseFacade::trace(new TraceBody(id: 'trace-1'))))->not->toHaveKey('userId');
});

it('uses a custom resolver bound by the application', function () {
    recordTraces();
    $this->app->instance(TraceContextResolverInterface::class, new class implements TraceContextResolverInterface
    {
        public function resolveUserId(): ?string
        {
            return 'tenant-7:user-3';
        }

        public function resolveSessionId(): ?string
        {
            return 'ticket-9';
        }
    });

    $body = traceBodyOf(LangfuseFacade::trace(new TraceBody(id: 'trace-1')));

    expect($body['userId'])->toBe('tenant-7:user-3')
        ->and($body['sessionId'])->toBe('ticket-9');
});

it('still creates the trace when the resolver throws', function () {
    recordTraces();
    $this->app->instance(TraceContextResolverInterface::class, new class implements TraceContextResolverInterface
    {
        public function resolveUserId(): ?string
        {
            throw new RuntimeException('guard exploded');
        }

        public function resolveSessionId(): ?string
        {
            return null;
        }
    });
    Log::shouldReceive('warning')->once()->with('Langfuse trace context resolution failed', ['message' => 'guard exploded']);

    $trace = LangfuseFacade::trace(new TraceBody(id: 'trace-1'));

    expect($trace->getId())->toBe(IdGenerator::traceIdFromSeed('trace-1'))
        ->and(traceBodyOf($trace))->not->toHaveKey('userId');
});

it('respects the user tracing switch', function () {
    config(['langfuse.user_tracing' => false]);
    $this->app->forgetInstance(LangfuseConfig::class);
    $this->app->forgetInstance(LangfuseClientInterface::class);
    recordTraces();
    $this->actingAs(new GenericUser(['id' => 42]));

    expect(traceBodyOf(LangfuseFacade::trace(new TraceBody(id: 'trace-1'))))->not->toHaveKey('userId');
});
