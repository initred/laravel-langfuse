<?php

declare(strict_types=1);

namespace Initred\Langfuse\Enums;

enum ObservationLevel: string
{
    case DEBUG = 'DEBUG';
    case DEFAULT = 'DEFAULT';
    case WARNING = 'WARNING';
    case ERROR = 'ERROR';
}
