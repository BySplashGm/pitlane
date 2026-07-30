<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Server;
use App\Exception\MissingContainerSlugException;
use RuntimeException;

interface DockerService
{
    /**
     * Current container state, one of `running`, `stopped`, `error` or `unknown`.
     *
     * @throws MissingContainerSlugException when the server has no container slug yet
     * @throws RuntimeException              on a Docker API failure
     */
    public function getContainerStatus(Server $server): string;

    /**
     * Container state for every given server, keyed by server id, in a single Docker API call.
     *
     * @param list<Server> $servers
     *
     * @return array<int, string> server id => status (`running`, `stopped`, `error` or `unknown`)
     *
     * @throws RuntimeException on a Docker API failure
     */
    public function getBulkStatus(array $servers): array;

    /**
     * Creates the container when it does not exist yet, then starts it.
     *
     * @throws MissingContainerSlugException when the server has no container slug yet
     * @throws RuntimeException              on a Docker API failure
     */
    public function startServer(Server $server): void;

    /**
     * @throws MissingContainerSlugException when the server has no container slug yet
     * @throws RuntimeException              on a Docker API failure
     */
    public function stopServer(Server $server): void;

    /**
     * @throws MissingContainerSlugException when the server has no container slug yet
     * @throws RuntimeException              on a Docker API failure
     */
    public function restartServer(Server $server): void;

    /**
     * Stops and force-removes the container; leaves the server's config files untouched.
     *
     * @throws MissingContainerSlugException when the server has no container slug yet
     * @throws RuntimeException              on a Docker API failure
     */
    public function removeContainer(Server $server): void;

    /**
     * Last `$tail` log lines, with Docker's multiplexed stream framing decoded.
     *
     * @throws MissingContainerSlugException when the server has no container slug yet
     * @throws RuntimeException              on a Docker API failure
     */
    public function getLogs(Server $server, int $tail = 200): string;
}
