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
use App\Service\AcConfigServiceInterface;
use App\Service\DockerServiceInterface;
use App\Tests\Support\ResetsDatabase;
use Doctrine\ORM\EntityManagerInterface;
use Override;
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

        // The dashboard reached after the redirect asks Docker for statuses; keep it off the socket.
        $dockerService = self::createStub(DockerServiceInterface::class);
        $dockerService->method('getBulkStatus')->willReturn([]);
        self::getContainer()->set(DockerServiceInterface::class, $dockerService);

        $this->kernelBrowser->loginUser($this->persistUser('owner@pitlane.test', UserRole::Owner));
        $this->submitForm($this->validPayload());

        self::assertResponseRedirects('/');

        // Following the redirect renders the dashboard, where the success flash is shown once.
        $this->kernelBrowser->followRedirect();
        self::assertSelectorTextContains('body', 'Server "Monza Cup" created.');

        $this->entityManager->clear();
        $server = $this->entityManager->getRepository(Server::class)->findOneBy(['name' => 'Monza Cup']);
        self::assertInstanceOf(Server::class, $server);
        self::assertSame(['ks_ferrari_488_gt3', 'ks_porsche_911'], $server->getCars());
        self::assertSame('monza-cup', $server->getContainerSlug());
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
