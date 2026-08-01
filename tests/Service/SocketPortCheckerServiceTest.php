<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\Server;
use App\Enum\DurationUnit;
use App\Enum\SessionType;
use App\Service\SocketPortCheckerService;
use InvalidArgumentException;
use Override;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class SocketPortCheckerServiceTest extends TestCase
{
    /**
     * @var array<int, resource>
     */
    private array $openSockets = [];

    #[DataProvider('loopbackAddressProvider')]
    public function test_check_port_returns_true_for_a_reachable_tcp_port(string $ip): void
    {
        $port = $this->openTcpServer($ip);

        self::assertTrue($this->makeService()->checkPort($ip, $port));
    }

    #[DataProvider('loopbackAddressProvider')]
    public function test_check_port_returns_false_for_a_closed_port(string $ip): void
    {
        $port = $this->openTcpServer($ip);
        $this->closeSockets();

        self::assertFalse($this->makeService()->checkPort($ip, $port));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function loopbackAddressProvider(): iterable
    {
        yield 'ipv4' => ['127.0.0.1'];
        yield 'ipv6' => ['::1'];
    }

    #[DataProvider('unsupportedProtocolProvider')]
    public function test_check_port_rejects_an_unsupported_protocol(string $protocol): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(\sprintf('Unsupported protocol "%s"; only "tcp" is checkable.', $protocol));

        $this->makeService()->checkPort('127.0.0.1', 9600, $protocol);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function unsupportedProtocolProvider(): iterable
    {
        yield 'udp is not checkable' => ['udp'];
        yield 'unknown scheme' => ['sctp'];
    }

    public function test_get_public_ip_returns_the_trimmed_resolver_body(): void
    {
        $mockResponse = new MockResponse("203.0.113.7\n", ['http_code' => 200]);

        self::assertSame('203.0.113.7', $this->makeService($mockResponse)->getPublicIp());
        self::assertSame('GET', $mockResponse->getRequestMethod());
        self::assertSame('https://api.ipify.org/', $mockResponse->getRequestUrl());

        $requestOptions = $mockResponse->getRequestOptions();
        self::assertSame(5.0, $requestOptions['timeout']);
        self::assertSame(5.0, $requestOptions['max_duration']);
    }

    public function test_get_public_ip_wraps_a_client_failure_in_a_runtime_exception(): void
    {
        $socketPortCheckerService = $this->makeService(new MockResponse('', ['error' => 'Connection refused']));

        $caught = null;
        try {
            $socketPortCheckerService->getPublicIp();
        } catch (RuntimeException $runtimeException) {
            $caught = $runtimeException;
        }

        self::assertInstanceOf(RuntimeException::class, $caught);
        self::assertStringStartsWith('Unable to resolve the host public IP:', $caught->getMessage());
    }

    public function test_check_server_reports_each_port_and_marks_udp_as_null(): void
    {
        $reachablePort = $this->openTcpServer();
        $closedPort = $this->openTcpServer();
        $this->closeSockets(only: $closedPort);

        $server = $this->createServer();
        $server->setTcpPort($reachablePort);
        $server->setHttpPort($closedPort);

        $result = $this->makeService(new MockResponse('127.0.0.1', ['http_code' => 200]))->checkServer($server);

        self::assertSame(['tcp' => true, 'udp' => null, 'http' => false], $result);
    }

    #[Override]
    protected function tearDown(): void
    {
        $this->closeSockets();
    }

    private function makeService(MockResponse ...$responses): SocketPortCheckerService
    {
        return new SocketPortCheckerService(new MockHttpClient($responses));
    }

    private function openTcpServer(string $ip = '127.0.0.1'): int
    {
        $host = str_contains($ip, ':') ? \sprintf('[%s]', $ip) : $ip;

        return $this->bindServer(\sprintf('tcp://%s:0', $host), \STREAM_SERVER_BIND | \STREAM_SERVER_LISTEN);
    }

    private function bindServer(string $address, int $flags): int
    {
        $server = stream_socket_server($address, $errorCode, $errorMessage, $flags);
        self::assertIsResource($server);

        $name = stream_socket_get_name($server, false);
        self::assertIsString($name);
        $parts = explode(':', $name);
        $port = (int) end($parts);

        $this->openSockets[$port] = $server;

        return $port;
    }

    private function closeSockets(?int $only = null): void
    {
        foreach ($this->openSockets as $port => $socket) {
            if (null !== $only && $only !== $port) {
                continue;
            }

            fclose($socket);
            unset($this->openSockets[$port]);
        }
    }

    private function createServer(): Server
    {
        return new Server(
            name: 'Spa Endurance',
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
    }
}
