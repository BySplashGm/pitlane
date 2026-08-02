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
use App\Exception\MissingContainerSlugException;
use App\Service\AcConfigServiceInterface;
use App\Service\DockerServiceInterface;
use App\Service\PortCheckerServiceInterface;
use App\Tests\Support\ResetsDatabase;
use Doctrine\ORM\EntityManagerInterface;
use Override;
use RuntimeException;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class ServerControllerTest extends WebTestCase
{
    use ResetsDatabase;

    private EntityManagerInterface $entityManager;

    private KernelBrowser $kernelBrowser;

    #[Override]
    protected function setUp(): void
    {
        $this->kernelBrowser = self::createClient();
        // Each test reads the form then posts it: keep one kernel so a service stub set before the
        // GET survives into the POST, and the stateless CSRF cookie stays consistent across both.
        $this->kernelBrowser->disableReboot();

        $this->entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $this->truncateUsers($this->entityManager);
        $this->truncateServers($this->entityManager);
    }

    public function test_the_form_prefills_the_next_available_ports(): void
    {
        $this->kernelBrowser->loginUser($this->persistUser('owner@pitlane.test', UserRole::Owner));
        $crawler = $this->kernelBrowser->request('GET', '/server/new');

        self::assertResponseIsSuccessful();
        self::assertSame('9600', $crawler->filter('input[name="server[tcpPort]"]')->attr('value'));
        self::assertSame('9601', $crawler->filter('input[name="server[udpPort]"]')->attr('value'));
        self::assertSame('9602', $crawler->filter('input[name="server[httpPort]"]')->attr('value'));
    }

    public function test_a_valid_submission_persists_the_server_writes_its_config_and_redirects(): void
    {
        $acConfigService = $this->createMock(AcConfigServiceInterface::class);
        $acConfigService->expects(self::once())
            ->method('writeConfig')
            ->with(self::isInstanceOf(Server::class));
        self::getContainer()->set(AcConfigServiceInterface::class, $acConfigService);

        // The detail page reached after the redirect probes Docker and the ports; keep both off the wire.
        $this->stubDocker(status: 'stopped');
        $this->stubPorts(['tcp' => true, 'udp' => null, 'http' => true]);

        $this->kernelBrowser->loginUser($this->persistUser('owner@pitlane.test', UserRole::Owner));
        $this->submitForm($this->validPayload());

        self::assertResponseRedirects();

        // Following the redirect renders the new server's detail page, showing the success flash once.
        $this->kernelBrowser->followRedirect();
        self::assertSelectorTextContains('h1', 'Monza Cup');
        self::assertSelectorTextContains('body', 'Server "Monza Cup" created.');

        $this->entityManager->clear();
        $server = $this->entityManager->getRepository(Server::class)->findOneBy(['name' => 'Monza Cup']);
        self::assertInstanceOf(Server::class, $server);
        self::assertSame(['ks_ferrari_488_gt3', 'ks_porsche_911'], $server->getCars());
        self::assertSame('monza-cup', $server->getContainerSlug());
    }

    public function test_the_detail_page_shows_the_server_with_a_running_log_box(): void
    {
        $server = $this->persistServer('Anderstorp Cup', portOffset: 1);

        $this->stubDocker(status: 'running');
        $this->stubPorts(['tcp' => true, 'udp' => null, 'http' => false]);

        $this->kernelBrowser->loginUser($this->persistUser('owner@pitlane.test', UserRole::Owner));
        $crawler = $this->kernelBrowser->request('GET', \sprintf('/server/%d', (int) $server->getId()));

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Anderstorp Cup');
        self::assertSelectorTextContains('body', 'running');
        self::assertSelectorTextContains('body', 'ks_ferrari_488_gt3');
        self::assertSelectorTextContains('body', '✅ Accessible');
        self::assertSelectorTextContains('body', '❌ Blocked');
        self::assertSelectorTextContains('body', 'Not probeable');
        // The owner may delete, and the running server exposes the polled log box.
        self::assertSelectorTextContains('body', 'Delete');
        self::assertCount(1, $crawler->filter('[data-logs]'));
    }

    public function test_the_detail_page_hides_the_log_box_when_the_server_is_not_running(): void
    {
        $server = $this->persistServer('Zolder Sprint', portOffset: 2);

        $this->stubDocker(status: 'stopped');
        $this->stubPorts(['tcp' => true, 'udp' => null, 'http' => true]);

        $this->kernelBrowser->loginUser($this->persistUser('owner@pitlane.test', UserRole::Owner));
        $crawler = $this->kernelBrowser->request('GET', \sprintf('/server/%d', (int) $server->getId()));

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'The server is not running.');
        self::assertCount(0, $crawler->filter('[data-logs]'));
    }

    public function test_the_detail_page_degrades_when_docker_and_the_port_probe_fail(): void
    {
        $server = $this->persistServer('Monza Trophy', portOffset: 3);

        $dockerService = self::createStub(DockerServiceInterface::class);
        $dockerService->method('getContainerStatus')->willThrowException(new RuntimeException('daemon down'));
        self::getContainer()->set(DockerServiceInterface::class, $dockerService);

        $portCheckerService = self::createStub(PortCheckerServiceInterface::class);
        $portCheckerService->method('checkServer')->willThrowException(new RuntimeException('no public ip'));
        self::getContainer()->set(PortCheckerServiceInterface::class, $portCheckerService);

        $this->kernelBrowser->loginUser($this->persistUser('owner@pitlane.test', UserRole::Owner));
        $this->kernelBrowser->request('GET', \sprintf('/server/%d', (int) $server->getId()));

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'unknown');
        self::assertSelectorTextContains('body', '⚠️ Unknown');
    }

    public function test_the_detail_page_degrades_when_the_container_slug_is_missing(): void
    {
        $server = $this->persistServer('Slugless Ring', portOffset: 8);

        $dockerService = self::createStub(DockerServiceInterface::class);
        $dockerService->method('getContainerStatus')->willThrowException(new MissingContainerSlugException());
        self::getContainer()->set(DockerServiceInterface::class, $dockerService);
        $this->stubPorts(['tcp' => true, 'udp' => null, 'http' => true]);

        $this->kernelBrowser->loginUser($this->persistUser('owner@pitlane.test', UserRole::Owner));
        $this->kernelBrowser->request('GET', \sprintf('/server/%d', (int) $server->getId()));

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'unknown');
    }

    public function test_an_operator_sees_an_assigned_server_but_not_the_delete_control(): void
    {
        $server = $this->persistServer('Assigned Ring', portOffset: 4);

        $user = $this->persistUser('operator@pitlane.test', UserRole::Operator);
        $user->assignServer($server);

        $this->entityManager->flush();

        $this->stubDocker(status: 'stopped');
        $this->stubPorts(['tcp' => true, 'udp' => null, 'http' => true]);

        $this->kernelBrowser->loginUser($user);
        $this->kernelBrowser->request('GET', \sprintf('/server/%d', (int) $server->getId()));

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Assigned Ring');
        $content = (string) $this->kernelBrowser->getResponse()->getContent();
        self::assertStringNotContainsString('Delete', $content);
    }

    public function test_an_operator_is_denied_an_unassigned_server(): void
    {
        $server = $this->persistServer('Unassigned Hills', portOffset: 5);

        $this->kernelBrowser->loginUser($this->persistUser('operator@pitlane.test', UserRole::Operator));
        $this->kernelBrowser->request('GET', \sprintf('/server/%d', (int) $server->getId()));

        self::assertResponseStatusCodeSame(403);
    }

    public function test_the_detail_page_returns_404_for_an_unknown_server(): void
    {
        $this->kernelBrowser->loginUser($this->persistUser('owner@pitlane.test', UserRole::Owner));
        $this->kernelBrowser->request('GET', '/server/999999');

        self::assertResponseStatusCodeSame(404);
    }

    public function test_the_logs_endpoint_returns_plain_text(): void
    {
        $server = $this->persistServer('Logged Loop', portOffset: 6);

        $this->stubDocker(status: 'running', logs: "lap 1 complete\nlap 2 complete");

        $this->kernelBrowser->loginUser($this->persistUser('owner@pitlane.test', UserRole::Owner));
        $this->kernelBrowser->request('GET', \sprintf('/server/%d/logs', (int) $server->getId()));

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('Content-Type', 'text/plain; charset=utf-8');
        self::assertSame("lap 1 complete\nlap 2 complete", (string) $this->kernelBrowser->getResponse()->getContent());
    }

    public function test_the_logs_endpoint_degrades_to_an_empty_body_on_a_docker_failure(): void
    {
        $server = $this->persistServer('Broken Loop', portOffset: 7);

        $dockerService = self::createStub(DockerServiceInterface::class);
        $dockerService->method('getLogs')->willThrowException(new RuntimeException('daemon down'));
        self::getContainer()->set(DockerServiceInterface::class, $dockerService);

        $this->kernelBrowser->loginUser($this->persistUser('owner@pitlane.test', UserRole::Owner));
        $this->kernelBrowser->request('GET', \sprintf('/server/%d/logs', (int) $server->getId()));

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('Content-Type', 'text/plain; charset=utf-8');
        self::assertSame('', (string) $this->kernelBrowser->getResponse()->getContent());
    }

    public function test_the_logs_endpoint_degrades_when_the_container_slug_is_missing(): void
    {
        $server = $this->persistServer('Slugless Loop', portOffset: 9);

        $dockerService = self::createStub(DockerServiceInterface::class);
        $dockerService->method('getLogs')->willThrowException(new MissingContainerSlugException());
        self::getContainer()->set(DockerServiceInterface::class, $dockerService);

        $this->kernelBrowser->loginUser($this->persistUser('owner@pitlane.test', UserRole::Owner));
        $this->kernelBrowser->request('GET', \sprintf('/server/%d/logs', (int) $server->getId()));

        self::assertResponseIsSuccessful();
        self::assertSame('', (string) $this->kernelBrowser->getResponse()->getContent());
    }

    public function test_an_invalid_submission_re_renders_without_persisting(): void
    {
        $acConfigService = $this->createMock(AcConfigServiceInterface::class);
        $acConfigService->expects(self::never())->method('writeConfig');
        self::getContainer()->set(AcConfigServiceInterface::class, $acConfigService);

        $this->kernelBrowser->loginUser($this->persistUser('owner@pitlane.test', UserRole::Owner));
        $payload = $this->validPayload();
        // Blank every required text field: each must yield a NotBlank error, never a 500 from a
        // null hitting the entity's typed setters.
        foreach (['name', 'serverName', 'track', 'adminPassword', 'weatherGraphics'] as $field) {
            $payload[$field] = '';
        }

        $this->submitForm($payload);

        self::assertResponseStatusCodeSame(422);
        self::assertCount(0, $this->entityManager->getRepository(Server::class)->findAll());
    }

    public function test_a_port_conflict_is_reported_without_persisting(): void
    {
        $this->persistServer('Existing Monza', portOffset: 0);

        $acConfigService = $this->createMock(AcConfigServiceInterface::class);
        $acConfigService->expects(self::never())->method('writeConfig');
        self::getContainer()->set(AcConfigServiceInterface::class, $acConfigService);

        $this->kernelBrowser->loginUser($this->persistUser('owner@pitlane.test', UserRole::Owner));
        $payload = $this->validPayload();
        // The seeded server already holds TCP 9600.
        $payload['tcpPort'] = '9600';
        $payload['udpPort'] = '9998';
        $payload['httpPort'] = '9999';
        $this->submitForm($payload);

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('body', 'already used by another server');
        self::assertCount(1, $this->entityManager->getRepository(Server::class)->findAll());
    }

    public function test_an_operator_is_denied_access(): void
    {
        $this->kernelBrowser->loginUser($this->persistUser('operator@pitlane.test', UserRole::Operator));
        $this->kernelBrowser->request('GET', '/server/new');

        self::assertResponseStatusCodeSame(403);
    }

    #[Override]
    protected function tearDown(): void
    {
        $this->truncateUsers($this->entityManager);
        $this->truncateServers($this->entityManager);
        parent::tearDown();
    }

    /**
     * @return array<string, string|list<string>>
     */
    private function validPayload(): array
    {
        return [
            'name' => 'Monza Cup',
            'serverName' => 'Pitlane Monza',
            'track' => 'monza',
            'trackLayout' => '',
            'cars' => ['ks_ferrari_488_gt3', 'ks_porsche_911'],
            'password' => '',
            'adminPassword' => 'admin-secret',
            'maxClients' => '20',
            'tcpPort' => '9600',
            'udpPort' => '9601',
            'httpPort' => '9602',
            'sessionType' => SessionType::Race->value,
            'sessionDuration' => '60',
            'durationUnit' => DurationUnit::Minutes->value,
            'weatherGraphics' => '3_clear',
            'ambientTemp' => '22',
            'trackTemp' => '28',
            'dynamicTrack' => '1',
            'trackGrip' => '96',
            'tcpNoDelay' => '1',
            'registerToLobby' => '1',
        ];
    }

    /**
     * @param array<string, string|list<string>> $payload
     */
    private function submitForm(array $payload): void
    {
        // Read the form first so the client holds the stateless CSRF cookie and its token.
        $crawler = $this->kernelBrowser->request('GET', '/server/new');
        $payload['_token'] = (string) $crawler->filter('input[name="server[_token]"]')->attr('value');

        $this->kernelBrowser->request('POST', '/server/new', ['server' => $payload]);
    }

    private function stubDocker(string $status, string $logs = ''): void
    {
        $dockerService = self::createStub(DockerServiceInterface::class);
        $dockerService->method('getContainerStatus')->willReturn($status);
        $dockerService->method('getLogs')->willReturn($logs);
        self::getContainer()->set(DockerServiceInterface::class, $dockerService);
    }

    /**
     * @param array{tcp: bool, udp: null, http: bool} $ports
     */
    private function stubPorts(array $ports): void
    {
        $portCheckerService = self::createStub(PortCheckerServiceInterface::class);
        $portCheckerService->method('checkServer')->willReturn($ports);
        self::getContainer()->set(PortCheckerServiceInterface::class, $portCheckerService);
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
