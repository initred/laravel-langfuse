<?php

declare(strict_types=1);

namespace Initred\Langfuse\Enums;

enum ScoreDataType: string
{
    case NUMERIC = 'NUMERIC';
    case BOOLEAN = 'BOOLEAN';
    case CATEGORICAL = 'CATEGORICAL';
    case TEXT = 'TEXT';
    case CORRECTION = 'CORRECTION';
}
