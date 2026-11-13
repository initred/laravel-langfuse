<?php

declare(strict_types=1);

use Initred\Langfuse\Version;

it('reports the latest released version from the changelog', function () {
    $changelog = (string) file_get_contents(__DIR__.'/../../CHANGELOG.md');

    preg_match('/^## \[(\d+\.\d+\.\d+)\]/m', $changelog, $matches);

    expect($matches[1] ?? null)->toBe(Version::SDK_VERSION);
});
