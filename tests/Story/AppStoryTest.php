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

namespace App\Tests\Story;

use App\Entity\Server;
use App\Entity\User;
use App\Enum\UserRole;
use App\Story\AppStory;
use Doctrine\ORM\EntityManagerInterface;
use Override;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Zenstruck\Foundry\Test\Factories;

final class AppStoryTest extends KernelTestCase
{
    use Factories;

    private EntityManagerInterface $entityManager;

    #[Override]
    protected function setUp(): void
    {
        self::bootKernel();

        $this->entityManager = self::getContainer()->get(EntityManagerInterface::class);

        new AppStory($this->entityManager)->build();

        // Read everything back from the database so a missing final flush leaves the
        // join-table assignments empty and fails the assertions below.
        $this->entityManager->clear();
    }

    public function test_it_creates_one_user_per_role(): void
    {
        self::assertSame(1, $this->countUsersWithRole(UserRole::Owner));
        self::assertSame(1, $this->countUsersWithRole(UserRole::Admin));
        self::assertSame(2, $this->countUsersWithRole(UserRole::Operator));
    }

    public function test_it_creates_six_servers_with_unique_sequential_ports(): void
    {
        self::assertSame(
            [9601, 9602, 9603, 9604, 9605, 9606],
            $this->allServerPorts(static fn (Server $server): int => $server->getTcpPort()),
        );
        self::assertSame(
            [9701, 9702, 9703, 9704, 9705, 9706],
            $this->allServerPorts(static fn (Server $server): int => $server->getUdpPort()),
        );
        self::assertSame(
            [8082, 8083, 8084, 8085, 8086, 8087],
            $this->allServerPorts(static fn (Server $server): int => $server->getHttpPort()),
        );
    }

    public function test_server_names_follow_the_race_server_sequence(): void
    {
        $names = array_map(static fn (Server $server): string => $server->getName(), $this->allServers());
        sort($names);

        self::assertSame(
            ['Race Server 1', 'Race Server 2', 'Race Server 3', 'Race Server 4', 'Race Server 5', 'Race Server 6'],
            $names,
        );
    }

    public function test_owner_is_assigned_every_server(): void
    {
        self::assertSame(
            [9601, 9602, 9603, 9604, 9605, 9606],
            $this->assignedTcpPorts('owner@pitlane.local'),
        );
    }

    public function test_operators_split_the_servers_in_two_halves(): void
    {
        self::assertSame([9601, 9602, 9603], $this->assignedTcpPorts('operator1@pitlane.local'));
        self::assertSame([9604, 9605, 9606], $this->assignedTcpPorts('operator2@pitlane.local'));
    }

    public function test_admin_is_assigned_no_server(): void
    {
        self::assertSame([], $this->assignedTcpPorts('admin@pitlane.local'));
    }

    private function countUsersWithRole(UserRole $userRole): int
    {
        return \count(array_filter(
            $this->entityManager->getRepository(User::class)->findAll(),
            static fn (User $user): bool => $user->getRole() === $userRole,
        ));
    }

    /**
     * @return array<int, Server>
     */
    private function allServers(): array
    {
        return $this->entityManager->getRepository(Server::class)->findAll();
    }

    /**
     * @param callable(Server): int $portGetter
     *
     * @return list<int>
     */
    private function allServerPorts(callable $portGetter): array
    {
        $ports = array_map($portGetter, $this->allServers());
        sort($ports);

        return $ports;
    }

    /**
     * @return list<int>
     */
    private function assignedTcpPorts(string $email): array
    {
        $user = $this->entityManager->getRepository(User::class)->findOneBy(['email' => $email]);
        self::assertInstanceOf(User::class, $user);

        $ports = array_map(
            static fn (Server $server): int => $server->getTcpPort(),
            $user->getAssignedServers()->toArray(),
        );
        sort($ports);

        return $ports;
    }
}
