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

namespace App\Tests\Factory;

use App\Enum\UserRole;
use App\Factory\UserFactory;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Zenstruck\Foundry\Test\Factories;

final class UserFactoryTest extends KernelTestCase
{
    use Factories;

    public function test_create_one_persists_a_user_with_the_default_attributes(): void
    {
        $user = UserFactory::createOne();

        self::assertSame('user@pitlane.local', $user->getEmail());
        self::assertSame(UserRole::Operator, $user->getRole());
    }

    public function test_created_user_password_is_hashed_and_verifies_against_the_default(): void
    {
        $user = UserFactory::createOne();

        $userPasswordHasher = self::getContainer()->get(UserPasswordHasherInterface::class);

        self::assertNotSame('password', $user->getPassword());
        self::assertTrue($userPasswordHasher->isPasswordValid($user, 'password'));
    }

    public function test_attributes_can_be_overridden(): void
    {
        $user = UserFactory::createOne(['email' => 'owner@pitlane.local', 'userRole' => UserRole::Owner]);

        self::assertSame('owner@pitlane.local', $user->getEmail());
        self::assertSame(UserRole::Owner, $user->getRole());
    }
}
