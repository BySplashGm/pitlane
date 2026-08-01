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

namespace App\Tests\Entity;

use App\Entity\Server;
use App\Enum\DurationUnit;
use App\Enum\SessionType;
use PHPUnit\Framework\TestCase;

final class ServerTest extends TestCase
{
    private function createServer(): Server
    {
        return new Server(
            name: 'Spa Endurance',
            serverName: 'Pitlane - Spa Endurance',
            track: 'spa',
            trackLayout: null,
            cars: ['ks_ferrari_488_gt3'],
            password: '',
            adminPassword: 'admin-secret',
            maxClients: 20,
            tcpPort: 9600,
            udpPort: 9601,
            httpPort: 8081,
            sessionType: SessionType::Race,
            sessionDuration: 60,
            durationUnit: DurationUnit::Minutes,
            weatherGraphics: '3_clear',
            ambientTemp: 22,
            trackTemp: 28,
            dynamicTrack: true,
            trackGrip: 96,
            tcpNoDelay: true,
            registerToLobby: true,
        );
    }

    public function test_new_server_exposes_constructor_values(): void
    {
        $server = $this->createServer();

        self::assertNull($server->getId());
        self::assertSame('Spa Endurance', $server->getName());
        self::assertSame('Pitlane - Spa Endurance', $server->getServerName());
        self::assertSame('spa', $server->getTrack());
        self::assertNull($server->getTrackLayout());
        self::assertSame(['ks_ferrari_488_gt3'], $server->getCars());
        self::assertSame('', $server->getPassword());
        self::assertSame('admin-secret', $server->getAdminPassword());
        self::assertSame(20, $server->getMaxClients());
        self::assertSame(9600, $server->getTcpPort());
        self::assertSame(9601, $server->getUdpPort());
        self::assertSame(8081, $server->getHttpPort());
        self::assertSame(SessionType::Race, $server->getSessionType());
        self::assertSame(60, $server->getSessionDuration());
        self::assertSame(DurationUnit::Minutes, $server->getDurationUnit());
        self::assertSame('3_clear', $server->getWeatherGraphics());
        self::assertSame(22, $server->getAmbientTemp());
        self::assertSame(28, $server->getTrackTemp());
        self::assertTrue($server->isDynamicTrack());
        self::assertSame(96, $server->getTrackGrip());
        self::assertTrue($server->isTcpNoDelay());
        self::assertTrue($server->isRegisterToLobby());
        self::assertSame('', $server->getContainerSlug());
        self::assertEqualsWithDelta(time(), $server->getCreatedAt()->getTimestamp(), 5);
        self::assertEquals($server->getCreatedAt(), $server->getUpdatedAt());
    }

    public function test_setters_update_each_field(): void
    {
        $server = $this->createServer();

        $server->setName('Monza Sprint');
        $server->setServerName('Pitlane - Monza Sprint');
        $server->setTrack('monza');
        $server->setTrackLayout('classic');
        $server->setCars(['ks_porsche_991_gt3_r']);
        $server->setPassword('secret');
        $server->setAdminPassword('new-admin-secret');
        $server->setMaxClients(24);
        $server->setTcpPort(9700);
        $server->setUdpPort(9701);
        $server->setHttpPort(8082);
        $server->setSessionType(SessionType::Qualify);
        $server->setSessionDuration(15);
        $server->setDurationUnit(DurationUnit::Laps);
        $server->setWeatherGraphics('7_heavy_clouds');
        $server->setAmbientTemp(18);
        $server->setTrackTemp(24);
        $server->setDynamicTrack(false);
        $server->setTrackGrip(90);
        $server->setTcpNoDelay(false);
        $server->setRegisterToLobby(false);

        self::assertSame('Monza Sprint', $server->getName());
        self::assertSame('Pitlane - Monza Sprint', $server->getServerName());
        self::assertSame('monza', $server->getTrack());
        self::assertSame('classic', $server->getTrackLayout());
        self::assertSame(['ks_porsche_991_gt3_r'], $server->getCars());
        self::assertSame('secret', $server->getPassword());
        self::assertSame('new-admin-secret', $server->getAdminPassword());
        self::assertSame(24, $server->getMaxClients());
        self::assertSame(9700, $server->getTcpPort());
        self::assertSame(9701, $server->getUdpPort());
        self::assertSame(8082, $server->getHttpPort());
        self::assertSame(SessionType::Qualify, $server->getSessionType());
        self::assertSame(15, $server->getSessionDuration());
        self::assertSame(DurationUnit::Laps, $server->getDurationUnit());
        self::assertSame('7_heavy_clouds', $server->getWeatherGraphics());
        self::assertSame(18, $server->getAmbientTemp());
        self::assertSame(24, $server->getTrackTemp());
        self::assertFalse($server->isDynamicTrack());
        self::assertSame(90, $server->getTrackGrip());
        self::assertFalse($server->isTcpNoDelay());
        self::assertFalse($server->isRegisterToLobby());
    }

    public function test_generate_container_slug_derives_from_name(): void
    {
        $server = $this->createServer();

        $server->generateContainerSlug();

        self::assertSame('spa-endurance', $server->getContainerSlug());
    }

    public function test_touch_updated_at_advances_the_timestamp(): void
    {
        $server = $this->createServer();
        $updatedAt = $server->getUpdatedAt();

        usleep(1_000);
        $server->touchUpdatedAt();

        self::assertGreaterThan($updatedAt, $server->getUpdatedAt());
    }
}
