# Laravel Langfuse

[![Latest Version](https://img.shields.io/packagist/v/initred/laravel-langfuse.svg?style=flat)](https://packagist.org/packages/initred/laravel-langfuse)
[![Total Downloads](https://img.shields.io/packagist/dt/initred/laravel-langfuse.svg?style=flat)](https://packagist.org/packages/initred/laravel-langfuse)
[![Tests](https://github.com/initred/laravel-langfuse/actions/workflows/tests.yml/badge.svg)](https://github.com/initred/laravel-langfuse/actions/workflows/tests.yml)
[![PHPStan](https://img.shields.io/badge/PHPStan-level%20max-brightgreen.svg?style=flat)](https://phpstan.org/)
[![PHP Version](https://img.shields.io/badge/PHP-8.5%2B-777BB4.svg?style=flat&logo=php)](https://www.php.net/)
[![Laravel](https://img.shields.io/badge/Laravel-13-FF2D20.svg?style=flat&logo=laravel)](https://laravel.com)
[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](https://opensource.org/licenses/MIT)

[Langfuse](https://langfuse.com) is an open-source observability platform for LLM applications. It gives you a dashboard to trace every LLM call, track token usage and costs, manage prompt versions, and evaluate output quality - all in one place. It's self-hostable or available as a managed cloud service.

This package connects your Laravel app to Langfuse, and is kept current with the latest [Laravel AI SDK](https://laravel.com/docs/ai-sdk) (`laravel/ai` 1.4+). Send traces, generations, scores, and prompts with a clean, idiomatic API - or let the auto-instrumentation do it for you.

```php
use Initred\Langfuse\LangfuseFacade as Langfuse;

$trace = Langfuse::trace(new TraceBody(name: 'chat-request'));

$generation = $trace->generation(new GenerationBody(
    name: 'chat',
    model: 'gpt-4',
    input: [['role' => 'user', 'content' => 'Hello!']],
));

// After the LLM responds:
$generation->end(
    output: 'Hi there!',
    usage: new Usage(input: 12, output: 85, total: 97),
);

$trace->end(output: 'Hi there!');
```

Observations are batched and flushed automatically. Zero-code auto-instrumentation is available for [Laravel AI](https://laravel.com/docs/ai-sdk) and [Neuron AI](https://github.com/neuron-core/neuron-ai).

## Features

- **Full observability** - traces, spans, generations, events, and scores with automatic parent-child nesting
- **Read & evaluate** - query scores, observations, and metrics, and manage datasets, dataset items and experiments to build evaluation workflows ([read API](docs/querying.md))
- **Prompt management** - fetch, cache, compile, create, and list prompts with stale-while-revalidate caching
- **Auto-instrumentation** - zero-code tracing for Laravel AI and Neuron AI
- **Automatic batching** - completed observations queued and sent in batches over OTLP, with optional async dispatch via Laravel queues
- **Production-ready** - Octane compatible, graceful degradation, auto-end and auto-flush on shutdown, testing fakes

## Installation

Requires PHP 8.5+ and Laravel 13. No PHP extensions beyond the core `hash`, `random_bytes` and `json`; `zlib` is only needed when you turn on `LANGFUSE_COMPRESSION`.

**Langfuse compatibility.** The package writes traces over OpenTelemetry (OTLP) and reads back from the v2/v3 APIs:

| Surface | Requirement |
|---|---|
| Tracing | Langfuse Cloud (v3 and v4) and self-hosted **3.22.0+** |
| Scores (write and read) | Langfuse Cloud (v3 and v4) and self-hosted v3+ |
| Observations, metrics and experiments read APIs | **Langfuse v4** |

```bash
composer require initred/laravel-langfuse
```

Add your Langfuse credentials to `.env`:

```env
LANGFUSE_PUBLIC_KEY=pk-lf-...
LANGFUSE_SECRET_KEY=sk-lf-...
```

Optionally publish the configuration file:

```bash
php artisan vendor:publish --tag=langfuse-config
```

## Documentation

Full documentation in the [`docs/`](docs/README.md) directory:

- [Configuration](docs/configuration.md) - env vars, config publishing
- [Tracing](docs/tracing.md) - traces, updating, nesting observations
- [Generations](docs/generations.md) - LLM generation tracking, usage and cost
- [Spans and Events](docs/spans-and-events.md) - non-LLM work, event logging
- [Scores](docs/scores.md) - numeric, boolean, categorical, text and correction scores
- [Prompt Management](docs/prompt-management.md) - fetch, cache, compile, create
- [Querying (read API)](docs/querying.md) - read scores/observations, metrics, and the experiment workflow
- [Integrations](docs/integrations/laravel-ai.md) - Laravel AI, Neuron AI
- [Middleware](docs/middleware.md) - request trace context
- [Batching and Flushing](docs/batching-and-flushing.md) - flush control, queued dispatch
- [Testing](docs/testing.md) - fakes and assertions
- [Architecture](docs/architecture.md) - system diagram, Octane compatibility
- [Troubleshooting](docs/troubleshooting.md) - Langfuse v4 tracing, common issues

See the [Changelog](CHANGELOG.md) for release history.

## Contributing

Contributions welcome. Open an issue first to discuss what you'd like to change, and see [CONTRIBUTING.md](.github/CONTRIBUTING.md) for the workflow.

```bash
composer test        # PHPStan, Pint check, type coverage and Pest
composer lint        # Fix code style
```

## License

MIT. See [LICENSE.md](LICENSE.md).

## Credits

Maintained by [initred](https://github.com/initred). Based on [axyr/laravel-langfuse](https://github.com/axyr/laravel-langfuse) by [Martijn van Nieuwenhoven](https://martijnvannieuwenhoven.com), used under the MIT license.