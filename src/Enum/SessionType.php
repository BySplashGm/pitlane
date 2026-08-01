<?php

declare(strict_types=1);

/*
 * This file is part of Pitlane.
 *
 * (c) Maxime Valin
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Enum;

enum SessionType: string
{
    case Practice = 'practice';
    case Qualify = 'qualify';
    case Race = 'race';
}
