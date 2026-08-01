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
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Override;

/**
 * @extends ServiceEntityRepository<Server>
 */
class DoctrineServerRepository extends ServiceEntityRepository implements ServerRepository
{
    public function __construct(ManagerRegistry $managerRegistry)
    {
        parent::__construct($managerRegistry, Server::class);
    }

    #[Override]
    public function findBySlug(string $containerSlug): ?Server
    {
        return $this->findOneBy(['containerSlug' => $containerSlug]);
    }
}
