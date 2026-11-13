<?php

declare(strict_types=1);

namespace Initred\Langfuse\Enums;

enum DatasetStatus: string
{
    case ACTIVE = 'ACTIVE';
    case ARCHIVED = 'ARCHIVED';
}
