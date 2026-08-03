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

namespace App\Tests\Repository;

use App\Entity\Server;
use App\Entity\User;
use App\Enum\DurationUnit;
use App\Enum\SessionType;
use App\Enum\UserRole;
use App\Repository\ServerRepository;
use App\Tests\Support\ResetsDatabase;
use Doctrine\ORM\EntityManagerInterface;
use Override;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class ServerRepositoryTest extends KernelTestCase
{
    use ResetsDatabase;

    private EntityManagerInterface $entityManager;

    private ServerRepository $serverRepository;

    #[Override]
    protected function setUp(): void
    {
        self::bootKernel();

        $this->entityManager = self::getContainer()->get(EntityManagerInterface::class);

        $this->serverRepository = self::getContainer()->get(ServerRepository::class);

        $this->truncateUsers($this->entityManager);
        $this->truncateServers($this->entityManager);
    }

    public function test_save_persists_and_flushes_the_server(): void
    {
        $server = $this->buildServer('Spa Endurance');

        $this->serverRepository->save($server);
        // Clearing drops every managed entity: the server is only found again if save() flushed it.
        $this->entityManager->clear();

        $reloaded = $this->serverRepository->findBySlug('spa-endurance');
        self::assertInstanceOf(Server::class, $reloaded);
        self::assertSame('Spa Endurance', $reloaded->getName());
    }

    public function test_find_by_slug_returns_null_when_no_server_matches(): void
    {
        // A server with a different slug is present so the finder must filter on the slug,
        // not just return the first row it finds.
        $this->persistServer('Spa Endurance');

        self::assertNull($this->serverRepository->findBySlug('unknown-slug'));
    }

    public function test_find_by_slug_returns_the_matching_server(): void
    {
        $this->persistServer('Spa Endurance');

        $reloaded = $this->serverRepository->findBySlug('spa-endurance');

        self::assertInstanceOf(Server::class, $reloaded);
        self::assertSame('Spa Endurance', $reloaded->getName());
    }

    public function test_find_all_ordered_by_name_returns_every_server_sorted(): void
    {
        $this->persistServer('Zolder Sprint', portOffset: 1);
        $this->persistServer('Anderstorp Cup', portOffset: 2);
        $this->persistServer('Monza Trophy', portOffset: 3);

        $names = array_map(
            static fn (Server $server): string => $server->getName(),
            $this->serverRepository->findAllOrderedByName(),
        );

        self::assertSame(['Anderstorp Cup', 'Monza Trophy', 'Zolder Sprint'], $names);
    }

    public function test_find_assigned_to_returns_only_the_users_servers_sorted(): void
    {
        $zolder = $this->persistServer('Zolder Sprint', portOffset: 1);
        $anderstorp = $this->persistServer('Anderstorp Cup', portOffset: 2);
        $monza = $this->persistServer('Monza Trophy', portOffset: 3);

        $operator = new User('operator@pitlane.test', UserRole::Operator);
        $operator->setPassword('hashed-password');
        $operator->assignServer($zolder);
        $operator->assignServer($anderstorp);

        // A second operator with a different assignment must not leak into the first one's result.
        $other = new User('other@pitlane.test', UserRole::Operator);
        $other->setPassword('hashed-password');
        $other->assignServer($monza);

        $this->entityManager->persist($operator);
        $this->entityManager->persist($other);
        $this->entityManager->flush();
        $this->entityManager->clear();

        $reloaded = $this->entityManager->getRepository(User::class)->findOneBy(['email' => 'operator@pitlane.test']);
        self::assertInstanceOf(User::class, $reloaded);

        $names = array_map(
            static fn (Server $server): string => $server->getName(),
            $this->serverRepository->findAssignedTo($reloaded),
        );

        self::assertSame(['Anderstorp Cup', 'Zolder Sprint'], $names);
    }

    #[Override]
    protected function tearDown(): void
    {
        $this->truncateUsers($this->entityManager);
        $this->truncateServers($this->entityManager);
        parent::tearDown();
    }

    private function persistServer(string $name, int $portOffset = 0): Server
    {
        $server = $this->buildServer($name, $portOffset);

        $this->entityManager->persist($server);
        $this->entityManager->flush();

        return $server;
    }

    private function buildServer(string $name, int $portOffset = 0): Server
    {
        $server = new Server(
            name: $name,
            serverName: 'Pitlane - Spa Endurance',
            track: 'spa',
            trackLayout: null,
            cars: ['ks_ferrari_488_gt3'],
            password: '',
            adminPassword: 'admin-secret',
            maxClients: 20,
            tcpPort: 9600 + $portOffset,
            udpPort: 9700 + $portOffset,
            httpPort: 8081 + $portOffset,
            sessionType: SessionType::Race,
            sessionDuration: 60,
            durationUnit: DurationUnit::Minutes,
            weatherGraphics: '3_clear',
            ambientTemp: 22,
            trackTemp: 28,
            dynamicTrack: true,
            trackGrip: 96,
            tcpNoDelay: true,
            registerToLobby: true,
        );
        $server->generateContainerSlug();

        return $server;
    }
}
