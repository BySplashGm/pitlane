<?php

declare(strict_types=1);

namespace App\Enum;

enum DurationUnit: string
{
    case Minutes = 'minutes';
    case Laps = 'laps';
}
