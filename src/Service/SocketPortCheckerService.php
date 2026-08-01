<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Server;
use InvalidArgumentException;
use Override;
use RuntimeException;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface as HttpClientExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final readonly class SocketPortCheckerService implements PortCheckerService
{
    private const string PUBLIC_IP_RESOLVER = 'https://api.ipify.org';

    private const float TIMEOUT_SECONDS = 2.0;

    public function __construct(
        private HttpClientInterface $httpClient,
    ) {
    }

    /**
     * @throws InvalidArgumentException
     */
    #[Override]
    public function checkPort(string $ip, int $port, string $protocol = 'tcp'): bool
    {
        // Only TCP is probed: a UDP "connect" completes without a handshake, so it would report an
        // unreachable port as open. Whitelisting the scheme also keeps an unexpected value from being
        // interpolated into the socket address and reinterpreting the target.
        if ('tcp' !== $protocol) {
            throw new InvalidArgumentException(\sprintf('Unsupported protocol "%s"; only "tcp" is checkable.', $protocol));
        }

        // STREAM_CLIENT_CONNECT plus the explicit timeout guarantee the call cannot block past
        // TIMEOUT_SECONDS; a failed connection surfaces as a false return, which we normalise.
        $stream = @stream_socket_client(
            \sprintf('%s://%s:%d', $protocol, $ip, $port),
            $errorCode,
            $errorMessage,
            self::TIMEOUT_SECONDS,
            \STREAM_CLIENT_CONNECT,
        );

        if (false === $stream) {
            return false;
        }

        fclose($stream);

        return true;
    }

    /**
     * @throws RuntimeException
     */
    #[Override]
    public function checkServer(Server $server): array
    {
        $publicIp = $this->getPublicIp();

        return [
            'tcp' => $this->checkPort($publicIp, $server->getTcpPort()),
            'udp' => null,
            'http' => $this->checkPort($publicIp, $server->getHttpPort()),
        ];
    }

    /**
     * @throws RuntimeException
     */
    #[Override]
    public function getPublicIp(): string
    {
        try {
            return trim($this->httpClient->request('GET', self::PUBLIC_IP_RESOLVER)->getContent());
        } catch (HttpClientExceptionInterface $httpClientException) {
            throw new RuntimeException(\sprintf('Unable to resolve the host public IP: %s', $httpClientException->getMessage()), $httpClientException->getCode(), previous: $httpClientException);
        }
    }
}
