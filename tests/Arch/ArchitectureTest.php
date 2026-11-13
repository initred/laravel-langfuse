<?php

declare(strict_types=1);

arch('enums are enums')
    ->expect('Initred\\Langfuse\\Enums')
    ->toBeEnums();

arch('enums are string-backed')
    ->expect('Initred\\Langfuse\\Enums')
    ->toBeStringBackedEnums();

arch('source uses strict types')
    ->expect('Initred\\Langfuse')
    ->toUseStrictTypes();

arch('dtos are readonly')
    ->expect('Initred\\Langfuse\\Dto')
    ->toBeReadonly();

arch('config is readonly')
    ->expect('Initred\\Langfuse\\Config')
    ->toBeReadonly();

arch('contracts are interfaces')
    ->expect('Initred\\Langfuse\\Contracts')
    ->toBeInterfaces();

arch('dtos do not depend on Laravel')
    ->expect('Initred\\Langfuse\\Dto')
    ->toUseNothing()
    ->ignoring('Initred\\Langfuse\\Contracts')
    ->ignoring('Initred\\Langfuse\\Enums')
    ->ignoring('Initred\\Langfuse\\Dto');

arch('only api clients use Http facade')
    ->expect('Illuminate\Support\Facades\Http')
    ->toOnlyBeUsedIn([
        'Initred\\Langfuse\\Api\IngestionApiClient',
        'Initred\\Langfuse\\Api\OtelTraceApiClient',
        'Initred\\Langfuse\\Api\PromptApiClient',
        'Initred\\Langfuse\\Api\ScoreApiClient',
        'Initred\\Langfuse\\Api\ObservationApiClient',
        'Initred\\Langfuse\\Api\MetricsApiClient',
        'Initred\\Langfuse\\Api\DatasetApiClient',
        'Initred\\Langfuse\\Api\DatasetItemApiClient',
        'Initred\\Langfuse\\Api\ExperimentApiClient',
    ]);

arch('facade extends base facade')
    ->expect('Initred\\Langfuse\\LangfuseFacade')
    ->toExtend('Illuminate\Support\Facades\Facade');

arch('service provider extends base provider')
    ->expect('Initred\\Langfuse\\LangfuseServiceProvider')
    ->toExtend('Illuminate\Support\ServiceProvider');

arch('dtos implement serializable interface')
    ->expect('Initred\\Langfuse\\Dto\TraceBody')
    ->toImplement('Initred\\Langfuse\\Contracts\SerializableInterface');

arch('client implements client interface')
    ->expect('Initred\\Langfuse\\LangfuseClient')
    ->toImplement('Initred\\Langfuse\\Contracts\LangfuseClientInterface');

arch('prompt dtos implement prompt interface')
    ->expect('Initred\\Langfuse\\Dto\TextPrompt')
    ->toImplement('Initred\\Langfuse\\Contracts\PromptInterface');

arch('chat prompt dtos implement prompt interface')
    ->expect('Initred\\Langfuse\\Dto\ChatPrompt')
    ->toImplement('Initred\\Langfuse\\Contracts\PromptInterface');

arch('exceptions extend RuntimeException')
    ->expect('Initred\\Langfuse\\Exceptions')
    ->toExtend('RuntimeException');

arch('fake implements client interface')
    ->expect('Initred\\Langfuse\\Testing\LangfuseFake')
    ->toImplement('Initred\\Langfuse\\Contracts\LangfuseClientInterface');

arch('recording batcher implements batcher interface')
    ->expect('Initred\\Langfuse\\Testing\RecordingEventBatcher')
    ->toImplement('Initred\\Langfuse\\Contracts\EventBatcherInterface');

arch('queued event batcher implements batcher interface')
    ->expect('Initred\\Langfuse\\Batch\QueuedEventBatcher')
    ->toImplement('Initred\\Langfuse\\Contracts\EventBatcherInterface');

arch('send ingestion batch job implements should queue')
    ->expect('Initred\\Langfuse\\Jobs\SendIngestionBatchJob')
    ->toImplement('Illuminate\\Contracts\\Queue\\ShouldQueue');

arch('send otel batch job implements should queue')
    ->expect('Initred\\Langfuse\\Jobs\SendOtelBatchJob')
    ->toImplement('Illuminate\\Contracts\\Queue\\ShouldQueue');

arch('event batcher implements batcher interface')
    ->expect('Initred\\Langfuse\\Batch\EventBatcher')
    ->toImplement('Initred\\Langfuse\\Contracts\EventBatcherInterface');

arch('null event batcher implements batcher interface')
    ->expect('Initred\\Langfuse\\Batch\NullEventBatcher')
    ->toImplement('Initred\\Langfuse\\Contracts\EventBatcherInterface');

arch('otel trace api client implements its interface')
    ->expect('Initred\\Langfuse\\Api\OtelTraceApiClient')
    ->toImplement('Initred\\Langfuse\\Contracts\OtelTraceApiClientInterface');

arch('experiment api client implements its interface')
    ->expect('Initred\\Langfuse\\Api\ExperimentApiClient')
    ->toImplement('Initred\\Langfuse\\Contracts\ExperimentApiClientInterface');

arch('observation objects end on shutdown')
    ->expect([
        'Initred\\Langfuse\\Objects\LangfuseTrace',
        'Initred\\Langfuse\\Objects\LangfuseSpan',
        'Initred\\Langfuse\\Objects\LangfuseGeneration',
    ])
    ->toImplement('Initred\\Langfuse\\Contracts\EndsOnShutdownInterface');

arch('null trace extends trace')
    ->expect('Initred\\Langfuse\\Objects\NullLangfuseTrace')
    ->toExtend('Initred\\Langfuse\\Objects\LangfuseTrace');

arch('null span extends span')
    ->expect('Initred\\Langfuse\\Objects\NullLangfuseSpan')
    ->toExtend('Initred\\Langfuse\\Objects\LangfuseSpan');

arch('null generation extends generation')
    ->expect('Initred\\Langfuse\\Objects\NullLangfuseGeneration')
    ->toExtend('Initred\\Langfuse\\Objects\LangfuseGeneration');
