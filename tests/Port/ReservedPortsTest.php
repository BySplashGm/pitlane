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

namespace App\Tests\Port;

use App\Port\ReservedPorts;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ReservedPortsTest extends TestCase
{
    #[DataProvider('reservedPorts')]
    public function test_contains_is_true_for_a_reserved_port(int $port): void
    {
        self::assertTrue(ReservedPorts::contains($port));
    }

    /**
     * @return iterable<string, array{int}>
     */
    public static function reservedPorts(): iterable
    {
        foreach (ReservedPorts::PORTS as $port) {
            yield (string) $port => [$port];
        }
    }

    #[DataProvider('freePorts')]
    public function test_contains_is_false_for_a_non_reserved_port(int $port): void
    {
        self::assertFalse(ReservedPorts::contains($port));
    }

    /**
     * @return iterable<string, array{int}>
     */
    public static function freePorts(): iterable
    {
        yield 'default base port' => [9600];
        yield 'one below a reserved port' => [7999];
        yield 'one above a reserved port' => [8081];
    }
}
