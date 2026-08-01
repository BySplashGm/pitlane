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

namespace App\Factory;

use App\Entity\User;
use App\Enum\UserRole;
use Override;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/**
 * @extends PersistentObjectFactory<User>
 */
final class UserFactory extends PersistentObjectFactory
{
    public const string DEFAULT_PASSWORD = 'password';

    public function __construct(private readonly UserPasswordHasherInterface $userPasswordHasher)
    {
        parent::__construct();
    }

    /**
     * @return class-string<User>
     */
    #[Override]
    public static function class(): string
    {
        return User::class;
    }

    /**
     * @return array<string, mixed>
     */
    #[Override]
    protected function defaults(): array
    {
        return [
            'email' => 'user@pitlane.local',
            'userRole' => UserRole::Operator,
        ];
    }

    #[Override]
    protected function initialize(): static
    {
        return $this->afterInstantiate(function (User $user): void {
            $user->setPassword($this->userPasswordHasher->hashPassword($user, self::DEFAULT_PASSWORD));
        });
    }
}
