<?php

declare(strict_types=1);

namespace App\Tests\Enum;

use App\Enum\UserRole;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class UserRoleTest extends TestCase
{
    #[DataProvider('role_names')]
    public function test_role_name(UserRole $userRole, string $expected): void
    {
        self::assertSame($expected, $userRole->roleName());
    }

    /**
     * @return iterable<string, array{UserRole, string}>
     */
    public static function role_names(): iterable
    {
        yield 'owner' => [UserRole::Owner, 'ROLE_OWNER'];
        yield 'admin' => [UserRole::Admin, 'ROLE_ADMIN'];
        yield 'operator' => [UserRole::Operator, 'ROLE_OPERATOR'];
    }
}
