<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\Server;
use App\Enum\DurationUnit;
use App\Enum\SessionType;
use App\Exception\MissingContainerSlugException;
use App\Service\DockerService;
use Closure;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use RuntimeException;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;

final class DockerServiceTest extends TestCase
{
    private const string SOCKET = '/var/run/docker.sock';

    private const string NETWORK = 'pitlane';

    private const string SERVERS_DIR = '/srv/ac';

    public function test_get_container_status_requests_the_inspect_endpoint(): void
    {
        $mockResponse = new MockResponse('{"State":{"Status":"running"}}', ['http_code' => 200]);

        $this->makeService($mockResponse)->getContainerStatus($this->createServer());

        self::assertSame('GET', $mockResponse->getRequestMethod());
        self::assertSame('http://docker/containers/spa-endurance/json', $mockResponse->getRequestUrl());

        $extraOptions = $mockResponse->getRequestOptions()['extra'];
        self::assertIsArray($extraOptions);
        $curlOptions = $extraOptions['curl'];
        self::assertIsArray($curlOptions);
        self::assertSame(self::SOCKET, $curlOptions[\CURLOPT_UNIX_SOCKET_PATH]);
    }

    public function test_a_transport_failure_is_wrapped_in_a_runtime_exception(): void
    {
        $dockerService = $this->makeService(new MockResponse('', ['error' => 'Connection refused']));

        $caught = null;
        try {
            $dockerService->getContainerStatus($this->createServer());
        } catch (RuntimeException $runtimeException) {
            $caught = $runtimeException;
        }

        self::assertInstanceOf(RuntimeException::class, $caught);
        self::assertSame('Docker API GET /containers/spa-endurance/json failed (transport error): Connection refused', $caught->getMessage());
        self::assertInstanceOf(TransportExceptionInterface::class, $caught->getPrevious());
    }

    #[DataProvider('stateMappingProvider')]
    public function test_get_container_status_maps_the_docker_state(string $inspectBody, string $expected): void
    {
        $dockerService = $this->makeService(new MockResponse($inspectBody, ['http_code' => 200]));

        self::assertSame($expected, $dockerService->getContainerStatus($this->createServer()));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function stateMappingProvider(): iterable
    {
        yield 'running' => ['{"State":{"Status":"running"}}', 'running'];
        yield 'created' => ['{"State":{"Status":"created"}}', 'stopped'];
        yield 'exited' => ['{"State":{"Status":"exited"}}', 'stopped'];
        yield 'paused' => ['{"State":{"Status":"paused"}}', 'stopped'];
        yield 'dead' => ['{"State":{"Status":"dead"}}', 'error'];
        yield 'restarting' => ['{"State":{"Status":"restarting"}}', 'error'];
        yield 'unrecognised status' => ['{"State":{"Status":"removing"}}', 'unknown'];
        yield 'missing status' => ['{"State":{}}', 'unknown'];
        yield 'missing state' => ['{}', 'unknown'];
    }

    public function test_get_container_status_treats_a_missing_container_as_stopped(): void
    {
        $dockerService = $this->makeService(new MockResponse('{"message":"No such container"}', ['http_code' => 404]));

        self::assertSame('stopped', $dockerService->getContainerStatus($this->createServer()));
    }

    public function test_get_container_status_throws_on_a_docker_error(): void
    {
        $dockerService = $this->makeService(new MockResponse('boom', ['http_code' => 500]));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Docker API GET /containers/spa-endurance/json failed (HTTP 500): boom');

        $dockerService->getContainerStatus($this->createServer());
    }

    public function test_a_response_just_below_300_is_treated_as_success(): void
    {
        $dockerService = $this->makeService(new MockResponse('{"State":{"Status":"running"}}', ['http_code' => 299]));

        self::assertSame('running', $dockerService->getContainerStatus($this->createServer()));
    }

    public function test_a_300_response_is_treated_as_an_error(): void
    {
        $dockerService = $this->makeService(new MockResponse('moved', ['http_code' => 300]));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('failed (HTTP 300)');

        $dockerService->getContainerStatus($this->createServer());
    }

    public function test_get_bulk_status_maps_every_server_by_container_name(): void
    {
        // The trailing entry has no "Names" key: it exercises the missing-name guard and matches no server.
        $mockResponse = new MockResponse('[{"Names":["/spa-endurance"],"State":"running"},{"Names":["/monza"]},{"State":"created"}]', ['http_code' => 200]);

        $statuses = $this->makeService($mockResponse)->getBulkStatus([
            $this->serverWithId(1, 'Spa Endurance'),
            $this->serverWithId(2, 'Other Track'),
            $this->serverWithId(3, 'Monza'),
        ]);

        self::assertSame([1 => 'running', 2 => 'stopped', 3 => 'unknown'], $statuses);
        self::assertSame('http://docker/containers/json?all=1', $mockResponse->getRequestUrl());
    }

    public function test_get_bulk_status_skips_servers_without_an_id(): void
    {
        $mockResponse = new MockResponse('[{"Names":["/monza"],"State":"running"}]', ['http_code' => 200]);

        // The id-less server comes first: it must be skipped without abandoning the servers after it.
        $statuses = $this->makeService($mockResponse)->getBulkStatus([
            $this->createServer(),
            $this->serverWithId(7, 'Monza'),
        ]);

        self::assertSame([7 => 'running'], $statuses);
    }

    public function test_start_creates_the_container_when_absent_then_starts_it(): void
    {
        $inspect = new MockResponse('{"message":"No such container"}', ['http_code' => 404]);
        $create = new MockResponse('{"Id":"abc"}', ['http_code' => 201]);
        $start = new MockResponse('', ['http_code' => 204]);

        $this->makeService($inspect, $create, $start)->startServer($this->createServer());

        self::assertSame('POST', $create->getRequestMethod());
        self::assertSame('http://docker/containers/create?name=spa-endurance', $create->getRequestUrl());

        $body = $create->getRequestOptions()['body'];
        self::assertIsString($body);
        self::assertSame([
            'Image' => 'ac-server:latest',
            'ExposedPorts' => ['9600/tcp' => [], '9601/udp' => [], '8081/tcp' => []],
            'HostConfig' => [
                'Binds' => ['/srv/ac/spa-endurance/cfg:/home/acserver/cfg:ro'],
                'PortBindings' => [
                    '9600/tcp' => [['HostPort' => '9600']],
                    '9601/udp' => [['HostPort' => '9601']],
                    '8081/tcp' => [['HostPort' => '8081']],
                ],
                'NetworkMode' => 'pitlane',
                'RestartPolicy' => ['Name' => 'unless-stopped'],
            ],
        ], json_decode($body, true));

        self::assertSame('POST', $start->getRequestMethod());
        self::assertSame('http://docker/containers/spa-endurance/start', $start->getRequestUrl());
    }

    public function test_start_skips_creation_when_the_container_already_exists(): void
    {
        $inspect = new MockResponse('{"State":{"Status":"exited"}}', ['http_code' => 200]);
        $start = new MockResponse('', ['http_code' => 204]);

        // Only two responses are provided: a create call would exhaust the mock and fail the test.
        $this->makeService($inspect, $start)->startServer($this->createServer());

        self::assertSame('http://docker/containers/spa-endurance/start', $start->getRequestUrl());
    }

    public function test_start_throws_when_inspecting_the_container_fails(): void
    {
        $dockerService = $this->makeService(new MockResponse('nope', ['http_code' => 500]));

        $this->expectException(RuntimeException::class);

        $dockerService->startServer($this->createServer());
    }

    public function test_stop_posts_to_the_stop_endpoint(): void
    {
        $mockResponse = new MockResponse('', ['http_code' => 204]);

        $this->makeService($mockResponse)->stopServer($this->createServer());

        self::assertSame('POST', $mockResponse->getRequestMethod());
        self::assertSame('http://docker/containers/spa-endurance/stop', $mockResponse->getRequestUrl());
    }

    public function test_stop_tolerates_an_already_stopped_container(): void
    {
        $mockResponse = new MockResponse('', ['http_code' => 304]);

        $this->makeService($mockResponse)->stopServer($this->createServer());

        self::assertSame('http://docker/containers/spa-endurance/stop', $mockResponse->getRequestUrl());
    }

    public function test_restart_posts_to_the_restart_endpoint(): void
    {
        $mockResponse = new MockResponse('', ['http_code' => 204]);

        $this->makeService($mockResponse)->restartServer($this->createServer());

        self::assertSame('POST', $mockResponse->getRequestMethod());
        self::assertSame('http://docker/containers/spa-endurance/restart', $mockResponse->getRequestUrl());
    }

    public function test_remove_force_deletes_the_container(): void
    {
        $mockResponse = new MockResponse('', ['http_code' => 204]);

        $this->makeService($mockResponse)->removeContainer($this->createServer());

        self::assertSame('DELETE', $mockResponse->getRequestMethod());
        self::assertSame('http://docker/containers/spa-endurance?force=true', $mockResponse->getRequestUrl());
    }

    public function test_remove_tolerates_a_missing_container(): void
    {
        $mockResponse = new MockResponse('{"message":"No such container"}', ['http_code' => 404]);

        $this->makeService($mockResponse)->removeContainer($this->createServer());

        self::assertSame('http://docker/containers/spa-endurance?force=true', $mockResponse->getRequestUrl());
    }

    public function test_get_logs_decodes_the_multiplexed_stream(): void
    {
        $mockResponse = new MockResponse($this->frame(1, 'hello ').$this->frame(2, 'world'), ['http_code' => 200]);

        self::assertSame('hello world', $this->makeService($mockResponse)->getLogs($this->createServer()));
        self::assertSame('http://docker/containers/spa-endurance/logs?stdout=1&stderr=1&tail=200', $mockResponse->getRequestUrl());
    }

    public function test_get_logs_honours_a_custom_tail(): void
    {
        $mockResponse = new MockResponse($this->frame(1, 'x'), ['http_code' => 200]);

        self::assertSame('x', $this->makeService($mockResponse)->getLogs($this->createServer(), 50));
        self::assertSame('http://docker/containers/spa-endurance/logs?stdout=1&stderr=1&tail=50', $mockResponse->getRequestUrl());
    }

    public function test_get_logs_returns_an_empty_string_without_output(): void
    {
        $dockerService = $this->makeService(new MockResponse('', ['http_code' => 200]));

        self::assertSame('', $dockerService->getLogs($this->createServer()));
    }

    #[DataProvider('slugRequiringCallProvider')]
    public function test_operations_reject_a_server_without_a_slug(Closure $call): void
    {
        $server = $this->createServer(withSlug: false);

        $this->expectException(MissingContainerSlugException::class);

        $call($this->makeService(), $server);
    }

    /**
     * @return iterable<string, array{Closure}>
     */
    public static function slugRequiringCallProvider(): iterable
    {
        yield 'status' => [static function (DockerService $dockerService, Server $server): void { $dockerService->getContainerStatus($server); }];
        yield 'start' => [static function (DockerService $dockerService, Server $server): void { $dockerService->startServer($server); }];
        yield 'stop' => [static function (DockerService $dockerService, Server $server): void { $dockerService->stopServer($server); }];
        yield 'restart' => [static function (DockerService $dockerService, Server $server): void { $dockerService->restartServer($server); }];
        yield 'remove' => [static function (DockerService $dockerService, Server $server): void { $dockerService->removeContainer($server); }];
        yield 'logs' => [static function (DockerService $dockerService, Server $server): void { $dockerService->getLogs($server); }];
    }

    private function makeService(MockResponse ...$responses): DockerService
    {
        return new DockerService(
            new MockHttpClient($responses),
            self::SOCKET,
            self::NETWORK,
            self::SERVERS_DIR,
        );
    }

    private function frame(int $streamType, string $payload): string
    {
        return pack('CxxxN', $streamType, \strlen($payload)).$payload;
    }

    private function createServer(string $name = 'Spa Endurance', bool $withSlug = true): Server
    {
        $server = new Server(
            name: $name,
            serverName: 'Pitlane - Spa Endurance',
            track: 'spa',
            trackLayout: null,
            cars: ['ks_ferrari_488_gt3', 'ks_bmw_z4_gt3'],
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

        if ($withSlug) {
            $server->generateContainerSlug();
        }

        return $server;
    }

    private function serverWithId(int $id, string $name): Server
    {
        $server = $this->createServer(name: $name);
        new ReflectionProperty(Server::class, 'id')->setValue($server, $id);

        return $server;
    }
}
