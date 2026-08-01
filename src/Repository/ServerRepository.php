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

use App\Entity\Server;
use App\Entity\User;
use Doctrine\Persistence\ObjectRepository;

/**
 * @extends ObjectRepository<Server>
 */
interface ServerRepository extends ObjectRepository
{
    /**
     * Persists the given server and flushes it to the database.
     */
    public function save(Server $server): void;

    public function findBySlug(string $containerSlug): ?Server;

    /**
     * Every server, ordered by name — the set an owner or admin sees.
     *
     * @return list<Server>
     */
    public function findAllOrderedByName(): array;

    /**
     * The servers assigned to the given user, ordered by name — an operator's scoped set.
     *
     * @return list<Server>
     */
    public function findAssignedTo(User $user): array;
}
