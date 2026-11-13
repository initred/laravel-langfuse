<?php

declare(strict_types=1);

namespace Initred\Langfuse\Exceptions;

use RuntimeException;

class PromptNotFoundException extends RuntimeException
{
    public static function forName(string $name): self
    {
        return new self("Prompt '{$name}' not found and no fallback provided.");
    }
}
