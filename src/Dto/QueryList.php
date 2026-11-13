<?php

declare(strict_types=1);

namespace Initred\Langfuse\Dto;

/**
 * The v3 and experiment endpoints take list filters as one comma-separated
 * value (OR within the filter), not as repeated keys.
 */
readonly class QueryList
{
    /**
     * @param  array<string|float|int>|null  $values
     */
    public static function csv(?array $values): ?string
    {
        if ($values === null || $values === []) {
            return null;
        }

        return implode(',', array_map(fn (string|float|int $value): string => (string) $value, $values));
    }
}
