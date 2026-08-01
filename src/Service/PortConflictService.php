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
use App\Repository\ServerRepository;
use Override;

final readonly class PortConflictService implements PortConflictServiceInterface
{
    public function __construct(
        private ServerRepository $serverRepository,
    ) {
    }

    #[Override]
    public function getUsedPorts(): array
    {
        $ports = [];

        foreach ($this->serverRepository->findAll() as $server) {
            $ports[] = $server->getTcpPort();
            $ports[] = $server->getUdpPort();
            $ports[] = $server->getHttpPort();
        }

        $ports = array_unique($ports);
        sort($ports);

        return $ports;
    }

    #[Override]
    public function hasConflict(Server $server): bool
    {
        return [] !== array_filter($this->getConflicts($server), static fn (?Server $server): bool => $server instanceof Server);
    }

    #[Override]
    public function getConflicts(Server $server): array
    {
        $owners = $this->portOwnersExcluding($server);

        return [
            'tcp' => $owners[$server->getTcpPort()] ?? null,
            'udp' => $owners[$server->getUdpPort()] ?? null,
            'http' => $owners[$server->getHttpPort()] ?? null,
        ];
    }

    #[Override]
    public function suggestNextAvailablePorts(int $startFrom = 9600): array
    {
        $usedPorts = array_flip($this->getUsedPorts());

        $tcp = $this->nextFreePort($startFrom, $usedPorts);
        $udp = $this->nextFreePort($tcp + 1, $usedPorts);
        $http = $this->nextFreePort($udp + 1, $usedPorts);

        return ['tcp' => $tcp, 'udp' => $udp, 'http' => $http];
    }

    /**
     * The first port at or above $from that is not in the used-port set.
     *
     * @param array<int, int> $usedPorts
     */
    private function nextFreePort(int $from, array $usedPorts): int
    {
        $candidate = $from;

        while (isset($usedPorts[$candidate])) {
            ++$candidate;
        }

        return $candidate;
    }

    /**
     * Maps every port held by a server other than $server to that owning server; the first server
     * seen for a port wins. The subject is skipped by identity and by id so an edit does not clash
     * with its own persisted row.
     *
     * @return array<int, Server>
     */
    private function portOwnersExcluding(Server $server): array
    {
        $owners = [];

        foreach ($this->serverRepository->findAll() as $other) {
            if ($other === $server) {
                continue;
            }

            if (null !== $server->getId() && $other->getId() === $server->getId()) {
                continue;
            }

            foreach ([$other->getTcpPort(), $other->getUdpPort(), $other->getHttpPort()] as $port) {
                $owners[$port] ??= $other;
            }
        }

        return $owners;
    }
}
