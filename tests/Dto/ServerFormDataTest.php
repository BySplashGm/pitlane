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

namespace App\Tests\Dto;

use App\Dto\ServerFormData;
use App\Enum\DurationUnit;
use App\Enum\SessionType;
use PHPUnit\Framework\TestCase;

final class ServerFormDataTest extends TestCase
{
    public function test_it_exposes_deterministic_defaults(): void
    {
        $serverFormData = new ServerFormData();

        self::assertSame('', $serverFormData->name);
        self::assertSame('', $serverFormData->serverName);
        self::assertSame('', $serverFormData->track);
        self::assertNull($serverFormData->trackLayout);
        self::assertSame([], $serverFormData->cars);
        self::assertNull($serverFormData->password);
        self::assertSame('', $serverFormData->adminPassword);
        self::assertSame(12, $serverFormData->maxClients);
        self::assertSame(0, $serverFormData->tcpPort);
        self::assertSame(0, $serverFormData->udpPort);
        self::assertSame(0, $serverFormData->httpPort);
        self::assertSame(SessionType::Race, $serverFormData->sessionType);
        self::assertSame(15, $serverFormData->sessionDuration);
        self::assertSame(DurationUnit::Minutes, $serverFormData->durationUnit);
        self::assertSame('', $serverFormData->weatherGraphics);
        self::assertSame(20, $serverFormData->ambientTemp);
        self::assertSame(26, $serverFormData->trackTemp);
        self::assertFalse($serverFormData->dynamicTrack);
        self::assertSame(100, $serverFormData->trackGrip);
        self::assertFalse($serverFormData->tcpNoDelay);
        self::assertFalse($serverFormData->registerToLobby);
    }

    public function test_to_server_maps_every_field(): void
    {
        $serverFormData = new ServerFormData();
        $serverFormData->name = 'Monza Cup';
        $serverFormData->serverName = 'Pitlane Monza';
        $serverFormData->track = 'monza';
        $serverFormData->trackLayout = 'monza_junior';
        $serverFormData->cars = ['ferrari_488', 'porsche_911'];
        $serverFormData->password = 'join-secret';
        $serverFormData->adminPassword = 'admin-secret';
        $serverFormData->maxClients = 18;
        $serverFormData->tcpPort = 9600;
        $serverFormData->udpPort = 9601;
        $serverFormData->httpPort = 9602;
        $serverFormData->sessionType = SessionType::Qualify;
        $serverFormData->sessionDuration = 30;
        $serverFormData->durationUnit = DurationUnit::Laps;
        $serverFormData->weatherGraphics = '3_clear';
        $serverFormData->ambientTemp = 24;
        $serverFormData->trackTemp = 30;
        $serverFormData->dynamicTrack = true;
        $serverFormData->trackGrip = 95;
        $serverFormData->tcpNoDelay = true;
        $serverFormData->registerToLobby = true;

        $server = $serverFormData->toServer();

        self::assertSame('Monza Cup', $server->getName());
        self::assertSame('Pitlane Monza', $server->getServerName());
        self::assertSame('monza', $server->getTrack());
        self::assertSame('monza_junior', $server->getTrackLayout());
        self::assertSame(['ferrari_488', 'porsche_911'], $server->getCars());
        self::assertSame('join-secret', $server->getPassword());
        self::assertSame('admin-secret', $server->getAdminPassword());
        self::assertSame(18, $server->getMaxClients());
        self::assertSame(9600, $server->getTcpPort());
        self::assertSame(9601, $server->getUdpPort());
        self::assertSame(9602, $server->getHttpPort());
        self::assertSame(SessionType::Qualify, $server->getSessionType());
        self::assertSame(30, $server->getSessionDuration());
        self::assertSame(DurationUnit::Laps, $server->getDurationUnit());
        self::assertSame('3_clear', $server->getWeatherGraphics());
        self::assertSame(24, $server->getAmbientTemp());
        self::assertSame(30, $server->getTrackTemp());
        self::assertTrue($server->isDynamicTrack());
        self::assertSame(95, $server->getTrackGrip());
        self::assertTrue($server->isTcpNoDelay());
        self::assertTrue($server->isRegisterToLobby());
    }

    public function test_to_server_defaults_a_blank_join_password_to_empty_string(): void
    {
        $serverFormData = new ServerFormData();
        $serverFormData->cars = ['ferrari_488'];

        self::assertSame('', $serverFormData->toServer()->getPassword());
    }
}
