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

namespace App\Service;

use App\Entity\Server;
use App\Exception\MissingContainerSlugException;
use RuntimeException;

interface DockerServiceInterface
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
