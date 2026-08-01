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

namespace App\Tests\Factory;

use App\Enum\DurationUnit;
use App\Enum\SessionType;
use App\Factory\ServerFactory;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Zenstruck\Foundry\Test\Factories;

final class ServerFactoryTest extends KernelTestCase
{
    use Factories;

    public function test_create_one_persists_a_server_with_the_default_attributes(): void
    {
        $server = ServerFactory::createOne();

        self::assertSame('Test Server', $server->getName());
        self::assertSame('Pitlane Test Server', $server->getServerName());
        self::assertSame('ks_nordschleife', $server->getTrack());
        self::assertNull($server->getTrackLayout());
        self::assertSame(['ks_ferrari_488_gt3'], $server->getCars());
        self::assertSame('', $server->getPassword());
        self::assertSame('admin-password', $server->getAdminPassword());
        self::assertSame(24, $server->getMaxClients());
        self::assertSame(9600, $server->getTcpPort());
        self::assertSame(9700, $server->getUdpPort());
        self::assertSame(8081, $server->getHttpPort());
        self::assertSame(SessionType::Race, $server->getSessionType());
        self::assertSame(20, $server->getSessionDuration());
        self::assertSame(DurationUnit::Minutes, $server->getDurationUnit());
        self::assertSame('3_clear', $server->getWeatherGraphics());
        self::assertSame(26, $server->getAmbientTemp());
        self::assertSame(30, $server->getTrackTemp());
        self::assertTrue($server->isDynamicTrack());
        self::assertSame(95, $server->getTrackGrip());
        self::assertTrue($server->isTcpNoDelay());
        self::assertFalse($server->isRegisterToLobby());
    }

    public function test_container_slug_is_generated_from_the_name(): void
    {
        $server = ServerFactory::createOne(['name' => 'My Race Server']);

        self::assertSame('my-race-server', $server->getContainerSlug());
    }
}
