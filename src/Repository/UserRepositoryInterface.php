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

namespace App\Repository;

use App\Entity\User;
use Doctrine\Persistence\ObjectRepository;
use Symfony\Component\Security\Core\User\PasswordUpgraderInterface;

/**
 * @extends ObjectRepository<User>
 */
interface UserRepositoryInterface extends ObjectRepository, PasswordUpgraderInterface
{
    public function ownerExists(): bool;

    /**
     * Persists the given user and flushes it to the database.
     */
    public function save(User $user): void;

    /**
     * Removes the given user and flushes the deletion to the database.
     */
    public function remove(User $user): void;

    /**
     * Every user, ordered by email — the set the user management page lists.
     *
     * @return list<User>
     */
    public function findAllOrderedByEmail(): array;
}
