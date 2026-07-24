<?php

declare(strict_types=1);

namespace App\Enum;

enum SessionType: string
{
    case Practice = 'practice';
    case Qualify = 'qualify';
    case Race = 'race';
}
