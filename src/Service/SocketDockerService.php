<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Server;
use App\Exception\MissingContainerSlugException;
use Override;
use RuntimeException;
use stdClass;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Filesystem\Path;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

final readonly class SocketDockerService implements DockerService
{
    private const string BASE_URI = 'http://docker';

    private const string IMAGE = 'ac-server:latest';

    private const string STATUS_RUNNING = 'running';

    private const string STATUS_STOPPED = 'stopped';

    private const string STATUS_ERROR = 'error';

    private const string STATUS_UNKNOWN = 'unknown';

    public function __construct(
        private HttpClientInterface $httpClient,
        #[Autowire('%env(DOCKER_SOCKET)%')]
        private string $dockerSocket,
        #[Autowire('%env(DOCKER_NETWORK)%')]
        private string $dockerNetwork,
        #[Autowire('%env(AC_SERVERS_DIR)%')]
        private string $acServersDir,
    ) {
    }

    /**
     * @throws MissingContainerSlugException
     * @throws RuntimeException
     */
    #[Override]
    public function getContainerStatus(Server $server): string
    {
        $response = $this->request('GET', \sprintf('/containers/%s/json', $this->containerName($server)), allowedStatusCode: 404);

        if (404 === $response->getStatusCode()) {
            return self::STATUS_STOPPED;
        }

        // request() has already guaranteed a success status, so decoding cannot hit an error response.
        /** @var array{State?: array{Status?: string}} $data */
        $data = $response->toArray();

        return $this->mapState($data['State']['Status'] ?? null);
    }

    /**
     * @throws RuntimeException
     */
    #[Override]
    public function getBulkStatus(array $servers): array
    {
        /** @var list<array{Names?: list<string>, State?: string}> $containers */
        $containers = $this->request('GET', '/containers/json?all=1')->toArray();

        $containersByName = [];
        foreach ($containers as $container) {
            foreach ($container['Names'] ?? [] as $name) {
                $containersByName[ltrim($name, '/')] = $container;
            }
        }

        $statuses = [];
        foreach ($servers as $server) {
            $id = $server->getId();
            if (null === $id) {
                continue;
            }

            $container = $containersByName[$server->getContainerSlug()] ?? null;
            $statuses[$id] = null === $container ? self::STATUS_STOPPED : $this->mapState($container['State'] ?? null);
        }

        return $statuses;
    }

    /**
     * @throws MissingContainerSlugException
     * @throws RuntimeException
     */
    #[Override]
    public function startServer(Server $server): void
    {
        $name = $this->containerName($server);

        $response = $this->request('GET', \sprintf('/containers/%s/json', $name), allowedStatusCode: 404);
        if (404 === $response->getStatusCode()) {
            $this->request('POST', \sprintf('/containers/create?name=%s', $name), $this->buildCreateConfig($server));
        }

        $this->request('POST', \sprintf('/containers/%s/start', $name));
    }

    /**
     * @throws MissingContainerSlugException
     * @throws RuntimeException
     */
    #[Override]
    public function stopServer(Server $server): void
    {
        $this->request('POST', \sprintf('/containers/%s/stop', $this->containerName($server)), allowedStatusCode: 304);
    }

    /**
     * @throws MissingContainerSlugException
     * @throws RuntimeException
     */
    #[Override]
    public function restartServer(Server $server): void
    {
        $this->request('POST', \sprintf('/containers/%s/restart', $this->containerName($server)));
    }

    /**
     * @throws MissingContainerSlugException
     * @throws RuntimeException
     */
    #[Override]
    public function removeContainer(Server $server): void
    {
        $this->request('DELETE', \sprintf('/containers/%s?force=true', $this->containerName($server)), allowedStatusCode: 404);
    }

    /**
     * @throws MissingContainerSlugException
     * @throws RuntimeException
     */
    #[Override]
    public function getLogs(Server $server, int $tail = 200): string
    {
        $response = $this->request('GET', \sprintf('/containers/%s/logs?stdout=1&stderr=1&tail=%d', $this->containerName($server), $tail));

        return $this->decodeStream($response->getContent());
    }

    /**
     * @param array<string, mixed>|null $body              JSON request payload, when the endpoint takes one
     * @param int|null                  $allowedStatusCode a single non-success code to accept instead of throwing (e.g. 404, 304)
     *
     * @throws RuntimeException on any Docker API response of 300 or above other than $allowedStatusCode
     */
    private function request(string $method, string $path, ?array $body = null, ?int $allowedStatusCode = null): ResponseInterface
    {
        // The daemon speaks HTTP over the mounted Unix socket, reached through cURL's dedicated
        // CURLOPT_UNIX_SOCKET_PATH; the local-interface 'bindto' option cannot address a socket file.
        // No 'throw' option: HTTP error codes are inspected directly (getStatusCode() only throws on
        // transport failure, and the body is read with throw=false), so we wrap transport errors ourselves.
        $options = ['extra' => ['curl' => [\CURLOPT_UNIX_SOCKET_PATH => $this->dockerSocket]]];
        if (null !== $body) {
            $options['json'] = $body;
        }

        try {
            $response = $this->httpClient->request($method, \sprintf('%s%s', self::BASE_URI, $path), $options);
            $statusCode = $response->getStatusCode();
        } catch (TransportExceptionInterface $transportException) {
            throw new RuntimeException(\sprintf('Docker API %s %s failed (transport error): %s', $method, $path, $transportException->getMessage()), $transportException->getCode(), previous: $transportException);
        }

        if ($statusCode >= 300 && $statusCode !== $allowedStatusCode) {
            // getStatusCode() above already fully resolved the response, so reading the buffered body
            // with throw=false cannot raise a fresh transport error here.
            throw new RuntimeException(\sprintf('Docker API %s %s failed (HTTP %d): %s', $method, $path, $statusCode, $response->getContent(false)));
        }

        return $response;
    }

    private function mapState(?string $state): string
    {
        return match ($state) {
            'running' => self::STATUS_RUNNING,
            'created', 'exited', 'paused' => self::STATUS_STOPPED,
            'dead', 'restarting' => self::STATUS_ERROR,
            default => self::STATUS_UNKNOWN,
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function buildCreateConfig(Server $server): array
    {
        $tcpPort = \sprintf('%d/tcp', $server->getTcpPort());
        $udpPort = \sprintf('%d/udp', $server->getUdpPort());
        $httpPort = \sprintf('%d/tcp', $server->getHttpPort());

        return [
            'Image' => self::IMAGE,
            'ExposedPorts' => [$tcpPort => new stdClass(), $udpPort => new stdClass(), $httpPort => new stdClass()],
            'HostConfig' => [
                'Binds' => [\sprintf('%s:/home/acserver/cfg:ro', Path::join($this->acServersDir, $server->getContainerSlug(), 'cfg'))],
                'PortBindings' => [
                    $tcpPort => [['HostPort' => (string) $server->getTcpPort()]],
                    $udpPort => [['HostPort' => (string) $server->getUdpPort()]],
                    $httpPort => [['HostPort' => (string) $server->getHttpPort()]],
                ],
                'NetworkMode' => $this->dockerNetwork,
                'RestartPolicy' => ['Name' => 'unless-stopped'],
            ],
        ];
    }

    /**
     * Decodes Docker's multiplexed stream, which the daemon emits as a sequence of complete frames:
     * an 8-byte header (1 byte stream type, 3 padding bytes, 4-byte big-endian payload size) then the payload.
     */
    private function decodeStream(string $stream): string
    {
        $output = '';
        $offset = 0;
        $length = \strlen($stream);

        while ($offset < $length) {
            // The payload size is the big-endian uint32 in the last 4 bytes of the 8-byte header.
            /** @var array{1: int} $header */
            $header = unpack('N', $stream, $offset + 4);
            $frameSize = $header[1];
            $offset += 8;
            $output .= substr($stream, $offset, $frameSize);
            $offset += $frameSize;
        }

        return $output;
    }

    /**
     * @throws MissingContainerSlugException when the server has no container slug yet
     */
    private function containerName(Server $server): string
    {
        $slug = $server->getContainerSlug();
        if ('' === $slug) {
            throw new MissingContainerSlugException();
        }

        return $slug;
    }
}
