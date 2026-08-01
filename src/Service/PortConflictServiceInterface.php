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

interface PortConflictServiceInterface
{
    /**
     * Every port occupied across all servers, ascending and de-duplicated.
     *
     * @return list<int>
     */
    public function getUsedPorts(): array;

    /**
     * Whether any of the given server's ports is already taken by another server.
     */
    public function hasConflict(Server $server): bool;

    /**
     * The other server holding each of the given server's ports, or null when the port is free.
     *
     * @return array{tcp: ?Server, udp: ?Server, http: ?Server}
     */
    public function getConflicts(Server $server): array;

    /**
     * The next free, distinct TCP/UDP/HTTP triplet, searching upward from $startFrom.
     *
     * @return array{tcp: int, udp: int, http: int}
     */
    public function suggestNextAvailablePorts(int $startFrom = 9600): array;
}
