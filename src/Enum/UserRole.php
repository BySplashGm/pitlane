<?php

declare(strict_types=1);

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
