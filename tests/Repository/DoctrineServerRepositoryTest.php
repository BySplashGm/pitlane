<?php

declare(strict_types=1);

namespace App\Tests\Repository;

use App\Entity\Server;
use App\Enum\DurationUnit;
use App\Enum\SessionType;
use App\Repository\DoctrineServerRepository;
use App\Tests\Support\ResetsDatabase;
use Doctrine\ORM\EntityManagerInterface;
use Override;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class DoctrineServerRepositoryTest extends KernelTestCase
{
    use ResetsDatabase;

    private EntityManagerInterface $entityManager;

    private DoctrineServerRepository $doctrineServerRepository;

    #[Override]
    protected function setUp(): void
    {
        self::bootKernel();

        $this->entityManager = self::getContainer()->get(EntityManagerInterface::class);

        $this->doctrineServerRepository = self::getContainer()->get(DoctrineServerRepository::class);

        $this->truncateServers($this->entityManager);
    }

    public function test_find_by_slug_returns_null_when_no_server_matches(): void
    {
        // A server with a different slug is present so the finder must filter on the slug,
        // not just return the first row it finds.
        $this->persistServer('Spa Endurance');

        self::assertNull($this->doctrineServerRepository->findBySlug('unknown-slug'));
    }

    public function test_find_by_slug_returns_the_matching_server(): void
    {
        $this->persistServer('Spa Endurance');

        $reloaded = $this->doctrineServerRepository->findBySlug('spa-endurance');

        self::assertInstanceOf(Server::class, $reloaded);
        self::assertSame('Spa Endurance', $reloaded->getName());
    }

    #[Override]
    protected function tearDown(): void
    {
        $this->truncateServers($this->entityManager);
        parent::tearDown();
    }

    private function persistServer(string $name): void
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
            tcpPort: 9600,
            udpPort: 9601,
            httpPort: 8081,
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

        $this->entityManager->persist($server);
        $this->entityManager->flush();
        $this->entityManager->clear();
    }
}
