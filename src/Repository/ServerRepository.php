<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Server;
use Doctrine\Persistence\ObjectRepository;

/**
 * @extends ObjectRepository<Server>
 */
interface ServerRepository extends ObjectRepository
{
    public function findBySlug(string $containerSlug): ?Server;
}
