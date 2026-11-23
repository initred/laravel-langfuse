# Changelog

All notable changes to `initred/laravel-langfuse` are documented here.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.1.0] - 2026-11-23

### Changed

- **Laravel AI conversation titles** - the call Laravel AI makes to title a new
  conversation lands on the same trace as the run it titles, with the same input,
  and was named after its model like the run's own generation. It is now named
  `conversation-title`.
- **Laravel AI generation metadata** - every generation records the agent class
  as `agent` in its metadata.

## [1.0.3] - 2026-11-23

### Fixed

- **Laravel AI tool failures** - a tool that throws sends `ToolFailed` instead of
  `ToolInvoked`, and since `laravel/ai` 1.4.1 so does a sub-agent run as a tool. Its
  span was never ended, so it was not sent to Langfuse and stayed in memory. It is
  now ended at the `ERROR` level with the exception message.
- **Laravel AI run failures** - when a run failed for good (`AgentFailed`), the
  trace the integration created was never ended and stayed the current trace, so
  the next run in the same process (Octane, a queue worker) was recorded on it. The
  failed run now records an `ERROR` generation, adds the message to the trace
  metadata as `error`, ends the trace and resets the current trace. A trace adopted
  from `LangfuseMiddleware` or `setCurrentTrace()` is still left open for its owner.

### Changed

- **Development dependencies** - tested against `laravel/ai` 1.4.1. With 1.4.1 the
  generation output of a `prompt()` call holds the text of every step, so an answer
  given in the same step as a tool call is no longer recorded as empty.
- **Development dependencies** - `phpstan/phpstan` is held at 2.3.5 until
  `pestphp/pest-plugin-type-coverage` supports 2.3.6. No runtime changes.

## [1.0.2] - 2026-11-16

### Changed

- **Development dependencies** - `paragonie/random_compat` is no longer installed
  in development. The dev constraint moved to `symfony/polyfill-uuid` ^1.20, the
  first release that no longer pulls it in. No runtime changes.

## [1.0.1] - 2026-11-13

### Fixed

- Packagist kept the first build of `v1.0.0` after the tag was moved, so the
  version installed from Packagist differed from the tagged commit. No code changes;
  install 1.0.1 to get the same code from GitHub and Packagist.

## [1.0.0] - 2026-11-13

First release. Based on [axyr/laravel-langfuse](https://github.com/axyr/laravel-langfuse)
0.4.0 by Martijn van Nieuwenhoven, used under the MIT license.

### Requirements

- PHP 8.5+ and Laravel 13 (`laravel/framework` ^13.0).
- Optional: `laravel/ai` ^1.4 for Laravel AI SDK tracing, `neuron-core/neuron-ai`
  ^4.1.18 for Neuron AI tracing (4.0 to 4.1.17 are marked as conflicting).

### Added

- Traces, spans, generations, events and scores sent to Langfuse v4 over the OTLP
  endpoint, batched and optionally dispatched on a queue.
- Read APIs for scores, observations, metrics, datasets and experiments.
- Prompt management with stale-while-revalidate caching and prompt-to-generation
  linking.
- Auto-instrumentation for the Laravel AI SDK and Neuron AI.
- Testing fakes and assertions.

### Differences from axyr/laravel-langfuse 0.4.0

- **Package and namespace** - `initred/laravel-langfuse` and `Initred\Langfuse`.
- **PHP 8.5 and Laravel 13** - older PHP and Laravel versions are not supported.
- **Laravel AI 1.x** - token usage is read from `inputTokens` / `outputTokens`.
  With `laravel/ai` 1.x the 0.x `promptTokens` property is gone and every traced
  agent call failed with an undefined property.
- **Prompt cache usage** - tokens read from and written to a prompt cache are moved
  out of `input` into `input_cached_tokens` and `input_cache_creation_tokens`, so
  Langfuse counts each token once and can price cache reads separately.
- **Prism tracing removed** - `laravel/ai` 1.x no longer runs on Prism. The Prism
  integration, the `prism_enabled` config key and `LANGFUSE_PRISM_ENABLED` are gone.
- **Neuron AI 4.x** - the observer is a PSR-14 listener for Neuron AI 4.x, subscribed
  with `$agent->subscribe(ObservabilityEvent::class, NeuronAiObserver::make())`,
  instead of an `ObserverInterface` for Neuron AI 3.x. The generation also records
  the provider's model and cached input tokens; tool spans record their inputs, are
  matched by call ID so parallel calls of the same tool stay apart, and a failed
  tool output marks its span as `ERROR`. A sub-agent sharing the observer no longer
  ends the outer trace.
- **Distribution** - tests, docs and tool configuration are left out of the
  installed package through `.gitattributes`.
