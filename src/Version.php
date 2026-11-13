<?php

declare(strict_types=1);

namespace Initred\Langfuse;

/**
 * Single source of truth for the SDK identity that Langfuse sees, used for the
 * OTLP resource attributes, the OTLP instrumentation scope and the score batch
 * metadata. Bump SDK_VERSION with every release: VersionTest checks it against the
 * newest released entry in CHANGELOG.md, and the release workflow against the tag.
 */
final class Version
{
    public const string SDK_NAME = 'langfuse-php';

    public const string SDK_LANGUAGE = 'php';

    public const string SDK_VERSION = '1.0.1';
}
