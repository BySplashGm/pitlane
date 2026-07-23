<?php

declare(strict_types=1);

namespace App\Tests\Entity;

use App\Entity\User;
use App\Enum\UserRole;
use PHPUnit\Framework\TestCase;

final class UserTest extends TestCase
{
    public function test_new_user_exposes_constructor_values(): void
    {
        $user = new User('owner@pitlane.test', UserRole::Owner);

        self::assertNull($user->getId());
        self::assertSame('owner@pitlane.test', $user->getEmail());
        self::assertSame('owner@pitlane.test', $user->getUserIdentifier());
        self::assertSame(UserRole::Owner, $user->getRole());
        self::assertEqualsWithDelta(time(), $user->getCreatedAt()->getTimestamp(), 5);
    }

    public function test_password_can_be_set(): void
    {
        $user = new User('owner@pitlane.test', UserRole::Owner);

        $user->setPassword('hashed-password');

        self::assertSame('hashed-password', $user->getPassword());
    }

    public function test_roles_include_role_specific_and_default_role(): void
    {
        $user = new User('admin@pitlane.test', UserRole::Admin);

        self::assertSame(['ROLE_ADMIN', 'ROLE_USER'], $user->getRoles());
    }
}
