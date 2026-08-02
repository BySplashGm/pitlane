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

namespace App\Port;

/**
 * The host ports the Pitlane stack and common services occupy. A managed server publishes its ports
 * on the host, so binding one of these would collide with the platform itself or a well-known daemon.
 *
 * Shared by the form validation (which rejects them) and the port suggestion (which skips them).
 */
final class ReservedPorts
{
    /**
     * Well-known services (SSH, SMTP, HTTP(S), MySQL, PostgreSQL, Redis, alt-HTTP) plus the Pitlane
     * stack's own host ports (app 8000, Mailpit 8025/1025).
     *
     * @var list<int>
     */
    public const array PORTS = [22, 25, 80, 443, 1025, 3306, 5432, 6379, 8000, 8025, 8080];

    public static function contains(int $port): bool
    {
        return \in_array($port, self::PORTS, true);
    }
}
