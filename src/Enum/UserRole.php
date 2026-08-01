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

enum UserRole: string
{
    case Owner = 'owner';
    case Admin = 'admin';
    case Operator = 'operator';

    public function roleName(): string
    {
        return match ($this) {
            self::Owner => 'ROLE_OWNER',
            self::Admin => 'ROLE_ADMIN',
            self::Operator => 'ROLE_OPERATOR',
        };
    }
}
