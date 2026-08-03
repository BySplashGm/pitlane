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
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\Query\Expr\Join;
use Doctrine\Persistence\ManagerRegistry;
use Override;

/**
 * @extends ServiceEntityRepository<Server>
 */
class ServerRepository extends ServiceEntityRepository implements ServerRepositoryInterface
{
    public function __construct(ManagerRegistry $managerRegistry)
    {
        parent::__construct($managerRegistry, Server::class);
    }

    #[Override]
    public function save(Server $server): void
    {
        $this->getEntityManager()->persist($server);
        $this->getEntityManager()->flush();
    }

    #[Override]
    public function findBySlug(string $containerSlug): ?Server
    {
        return $this->findOneBy(['containerSlug' => $containerSlug]);
    }

    #[Override]
    public function findAllOrderedByName(): array
    {
        return $this->findBy([], ['name' => 'ASC']);
    }

    #[Override]
    public function findAssignedTo(User $user): array
    {
        // Server has no inverse side of the assignment, so the join is expressed from the owning
        // User association with DQL's MEMBER OF operator.
        /** @var list<Server> $servers */
        $servers = $this->createQueryBuilder('server')
            ->innerJoin(User::class, 'assignee', Join::WITH, 'server MEMBER OF assignee.assignedServers')
            ->where('assignee = :user')
            ->setParameter('user', $user)
            ->orderBy('server.name', 'ASC')
            ->getQuery()
            ->getResult();

        return $servers;
    }
}
