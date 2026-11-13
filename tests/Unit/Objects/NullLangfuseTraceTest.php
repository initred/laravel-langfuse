<?php

declare(strict_types=1);

use Initred\Langfuse\Dto\EventBody;
use Initred\Langfuse\Dto\GenerationBody;
use Initred\Langfuse\Dto\ScoreBody;
use Initred\Langfuse\Dto\SpanBody;
use Initred\Langfuse\Dto\TraceBody;
use Initred\Langfuse\Objects\NullLangfuseGeneration;
use Initred\Langfuse\Objects\NullLangfuseSpan;
use Initred\Langfuse\Objects\NullLangfuseTrace;

it('does not enqueue events on construction', function () {
    $trace = new NullLangfuseTrace;

    expect($trace)->toBeInstanceOf(NullLangfuseTrace::class);
});

it('returns empty string for getId', function () {
    $trace = new NullLangfuseTrace;

    expect($trace->getId())->toBe('');
});

it('update is a no-op', function () {
    $trace = new NullLangfuseTrace;

    $trace->update(new TraceBody(name: 'updated'));

    expect($trace->getId())->toBe('');
});

it('span returns a NullLangfuseSpan', function () {
    $trace = new NullLangfuseTrace;

    $span = $trace->span(new SpanBody(name: 'child'));

    expect($span)->toBeInstanceOf(NullLangfuseSpan::class);
});

it('generation returns a NullLangfuseGeneration', function () {
    $trace = new NullLangfuseTrace;

    $generation = $trace->generation(new GenerationBody(name: 'gen'));

    expect($generation)->toBeInstanceOf(NullLangfuseGeneration::class);
});

it('event is a no-op', function () {
    $trace = new NullLangfuseTrace;

    $trace->event(new EventBody(name: 'test'));

    expect($trace->getId())->toBe('');
});

it('score is a no-op', function () {
    $trace = new NullLangfuseTrace;

    $trace->score(new ScoreBody(name: 'accuracy', traceId: 'trace-1', value: 0.9));

    expect($trace->getId())->toBe('');
});

it('returns an empty root observation id', function () {
    expect((new NullLangfuseTrace)->getRootObservationId())->toBe('');
});

it('end and endOnShutdown are no-ops and it always reports as ended', function () {
    $trace = new NullLangfuseTrace;

    $trace->end(output: 'done');
    $trace->endOnShutdown();

    expect($trace->hasEnded())->toBeTrue();
});
