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

namespace App\Tests\Controller;

use App\Entity\Server;
use App\Entity\User;
use App\Enum\DurationUnit;
use App\Enum\SessionType;
use App\Enum\UserRole;
use App\Service\DockerServiceInterface;
use App\Tests\Support\ResetsDatabase;
use Doctrine\ORM\EntityManagerInterface;
use Override;
use RuntimeException;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class DashboardControllerTest extends WebTestCase
{
    use ResetsDatabase;

    private EntityManagerInterface $entityManager;

    private KernelBrowser $kernelBrowser;

    #[Override]
    protected function setUp(): void
    {
        $this->kernelBrowser = self::createClient();

        $this->entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $this->truncateUsers($this->entityManager);
        $this->truncateServers($this->entityManager);
    }

    public function test_owner_sees_every_server_with_its_status_and_summary_counts(): void
    {
        $server = $this->persistServer('Anderstorp Cup', portOffset: 1);
        $stopped = $this->persistServer('Monza Trophy', portOffset: 2);
        $errored = $this->persistServer('Zolder Sprint', portOffset: 3);

        $this->stubBulkStatus([
            (int) $server->getId() => 'running',
            (int) $stopped->getId() => 'stopped',
            (int) $errored->getId() => 'error',
        ]);

        $this->kernelBrowser->loginUser($this->persistUser('owner@pitlane.test', UserRole::Owner));
        $crawler = $this->kernelBrowser->request('GET', '/');

        self::assertResponseIsSuccessful();
        self::assertCount(3, $crawler->filter('article'));
        self::assertSame('3', trim($crawler->filter('[data-stat="total"]')->text()));
        self::assertSame('1', trim($crawler->filter('[data-stat="running"]')->text()));
        self::assertSame('1', trim($crawler->filter('[data-stat="stopped"]')->text()));
        self::assertSelectorTextContains('body', 'Anderstorp Cup');
        self::assertSelectorTextContains('body', 'running');
        self::assertSelectorTextContains('body', 'error');
    }

    public function test_operator_sees_only_the_servers_assigned_to_them(): void
    {
        $server = $this->persistServer('Anderstorp Cup', portOffset: 1);
        $this->persistServer('Monza Trophy', portOffset: 2);

        $user = $this->persistUser('operator@pitlane.test', UserRole::Operator);
        $user->assignServer($server);

        $this->entityManager->flush();

        $this->stubBulkStatus([(int) $server->getId() => 'running']);

        $this->kernelBrowser->loginUser($user);
        $crawler = $this->kernelBrowser->request('GET', '/');

        self::assertResponseIsSuccessful();
        self::assertCount(1, $crawler->filter('article'));
        self::assertSelectorTextContains('[data-stat="total"]', '1');
        self::assertSelectorTextContains('body', 'Anderstorp Cup');
        $content = (string) $this->kernelBrowser->getResponse()->getContent();
        self::assertStringNotContainsString('Monza Trophy', $content);
    }

    public function test_the_empty_state_shows_when_no_server_is_accessible(): void
    {
        $this->persistServer('Monza Trophy', portOffset: 1);

        $this->stubBulkStatus([]);

        $this->kernelBrowser->loginUser($this->persistUser('operator@pitlane.test', UserRole::Operator));
        $crawler = $this->kernelBrowser->request('GET', '/');

        self::assertResponseIsSuccessful();
        self::assertCount(0, $crawler->filter('article'));
        self::assertSelectorTextContains('[data-stat="total"]', '0');
        self::assertSelectorTextContains('body', 'No servers yet');
    }

    public function test_a_docker_failure_degrades_every_server_to_an_unknown_status(): void
    {
        $this->persistServer('Monza Trophy', portOffset: 1);

        $dockerService = self::createStub(DockerServiceInterface::class);
        $dockerService->method('getBulkStatus')->willThrowException(new RuntimeException('daemon down'));
        self::getContainer()->set(DockerServiceInterface::class, $dockerService);

        $this->kernelBrowser->loginUser($this->persistUser('owner@pitlane.test', UserRole::Owner));
        $this->kernelBrowser->request('GET', '/');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('[data-stat="total"]', '1');
        self::assertSelectorTextContains('[data-stat="running"]', '0');
        self::assertSelectorTextContains('[data-stat="stopped"]', '0');
        self::assertSelectorTextContains('article', 'unknown');
    }

    #[Override]
    protected function tearDown(): void
    {
        $this->truncateUsers($this->entityManager);
        $this->truncateServers($this->entityManager);
        parent::tearDown();
    }

    /**
     * @param array<int, string> $statuses
     */
    private function stubBulkStatus(array $statuses): void
    {
        $dockerService = self::createStub(DockerServiceInterface::class);
        $dockerService->method('getBulkStatus')->willReturn($statuses);
        self::getContainer()->set(DockerServiceInterface::class, $dockerService);
    }

    private function persistUser(string $email, UserRole $userRole): User
    {
        $user = new User($email, $userRole);
        $user->setPassword('hashed-password');

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        return $user;
    }

    private function persistServer(string $name, int $portOffset = 0): Server
    {
        $server = new Server(
            name: $name,
            serverName: \sprintf('Pitlane - %s', $name),
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

        $this->entityManager->persist($server);
        $this->entityManager->flush();

        return $server;
    }
}
