[Back to documentation](../README.md)

# Neuron AI Integration

Event-listener tracing for [Neuron AI](https://github.com/neuron-core/neuron-ai) agents, RAGs and workflows.

Requires `neuron-core/neuron-ai` 4.1.18 or later. Neuron AI 3.x is not supported.

## Setup

Enable tracing:

```env
LANGFUSE_NEURON_AI_ENABLED=true
```

Subscribe the observer to every observability event of your agent:

```php
use Initred\Langfuse\NeuronAi\NeuronAiObserver;
use NeuronAI\Chat\Messages\UserMessage;
use NeuronAI\Observability\ObservabilityEvent;

$agent = MyAgent::make()
    ->setThreadId($threadId)
    ->subscribe(ObservabilityEvent::class, NeuronAiObserver::make());

$state = $agent->chat(new UserMessage('Hello!'));
```

Or subscribe it where the agent is built, so every instance is traced:

```php
use Initred\Langfuse\NeuronAi\NeuronAiObserver;
use NeuronAI\Agent\Agent;
use NeuronAI\Observability\ObservabilityEvent;
use NeuronAI\Workflow\WorkflowState;

class MyAgent extends Agent
{
    public function __construct(?string $workflowId = null, ?WorkflowState $state = null)
    {
        parent::__construct($workflowId, $state);

        $this->subscribe(ObservabilityEvent::class, NeuronAiObserver::make());
    }
}
```

Listeners stay on the instance across runs, so subscribe once per instance - not before every `chat()` call, or each event is recorded more than once.

### Upgrading from Neuron AI 3.x

- Replace `$agent->observe(NeuronAiObserver::make())` with `$agent->subscribe(ObservabilityEvent::class, NeuronAiObserver::make())`. The observer is now a PSR-14 listener and no longer implements the deprecated `ObserverInterface`.
- Move the registration out of loops and per-run code (see above).

## What gets traced

The observer maps Neuron AI's event lifecycle to Langfuse:

- **Workflow start/end** - creates a trace, then sets the workflow state as its output and ends it. Neuron AI dispatches the end event when a run completes, is suspended or fails.
- **Inference** - creates a generation with input, response, model and token usage, and ends it. Tokens read from a prompt cache are moved out of `input` into `input_cached_tokens`, so Langfuse counts each token once.
- **Tool calls** - creates a span per tool call with its inputs and result, marked with the `tool` observation type, and ends it. Parallel calls of the same tool are matched by call ID; a tool output flagged as an error sets the span level to `ERROR`.
- **RAG retrieval** - creates a span with query and document count, marked with the `retriever` observation type, and ends it
- **Errors** - records the exception on the trace; the workflow end that follows ends it

## Trace lifecycle

An observation is only sent when it ends. The observer ends every generation and span it creates, and ends the trace on the outermost `workflow-end` - but only when it created that trace itself. A sub-agent that shares the observer runs inside the outer trace and does not close it.

A trace it adopted - from `LangfuseMiddleware` or from your own `setCurrentTrace()` - is left open for its owner, so a workflow nested inside a larger unit of work does not close the enclosing trace.

## Request grouping

If you use `LangfuseMiddleware`, Neuron AI observations nest under the request trace automatically. In a queue worker or a CLI command, call `Langfuse::shutdown()` when the job is done.

---

Previous: [Laravel AI Integration](laravel-ai.md) | Next: [Middleware](../middleware.md)

Need help? See [Troubleshooting](../troubleshooting.md)
