<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Server;
use RuntimeException;

interface PortCheckerService
{
    /**
     * Attempts a connection to the given port with a strict timeout and never hangs.
     *
     * @param 'tcp'|'udp' $protocol transport scheme to probe with
     *
     * @return bool true when the connection succeeds within the timeout, false otherwise
     */
    public function checkPort(string $ip, int $port, string $protocol = 'tcp'): bool;

    /**
     * Live reachability of the server's ports from the host's public IP.
     *
     * UDP is connectionless, so an outward probe is meaningless: it is reported as `null`
     * (not checkable) rather than a boolean.
     *
     * @return array{tcp: bool, udp: null, http: bool}
     *
     * @throws RuntimeException on a failure to resolve the host's public IP
     */
    public function checkServer(Server $server): array;

    /**
     * The host machine's public IP, fetched live from an external resolver.
     *
     * @throws RuntimeException on a transport failure or non-success response
     */
    public function getPublicIp(): string;
}
