# Changelog

All notable changes to `initred/laravel-langfuse` are documented here.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

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
