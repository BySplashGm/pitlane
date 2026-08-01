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
use Override;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class ServerValidationTest extends KernelTestCase
{
    private ValidatorInterface $validator;

    #[Override]
    protected function setUp(): void
    {
        self::bootKernel();

        $this->validator = self::getContainer()->get(ValidatorInterface::class);
    }

    public function test_a_fully_valid_server_has_no_violations(): void
    {
        self::assertCount(0, $this->validator->validate(self::buildServer()));
    }

    #[DataProvider('invalid_servers')]
    public function test_invalid_server_is_rejected(Server $server, string $expectedPropertyPath): void
    {
        $constraintViolationList = $this->validator->validate($server);

        self::assertGreaterThan(0, $constraintViolationList->count());

        $propertyPaths = [];
        foreach ($constraintViolationList as $violation) {
            $propertyPaths[] = $violation->getPropertyPath();
        }

        self::assertContains($expectedPropertyPath, $propertyPaths);
    }

    /**
     * @return iterable<string, array{Server, string}>
     */
    public static function invalid_servers(): iterable
    {
        yield 'blank name' => [self::buildServer(name: ''), 'name'];
        yield 'blank server name' => [self::buildServer(serverName: ''), 'serverName'];
        yield 'blank track' => [self::buildServer(track: ''), 'track'];
        yield 'track layout too long' => [self::buildServer(trackLayout: str_repeat('a', 256)), 'trackLayout'];
        yield 'no cars selected' => [self::buildServer(cars: []), 'cars'];
        yield 'blank car entry' => [self::buildServer(cars: ['']), 'cars[0]'];
        yield 'blank admin password' => [self::buildServer(adminPassword: ''), 'adminPassword'];
        yield 'max clients too low' => [self::buildServer(maxClients: 0), 'maxClients'];
        yield 'max clients too high' => [self::buildServer(maxClients: 25), 'maxClients'];
        yield 'tcp port too low' => [self::buildServer(tcpPort: 1023), 'tcpPort'];
        yield 'tcp port too high' => [self::buildServer(tcpPort: 65536), 'tcpPort'];
        yield 'udp port too low' => [self::buildServer(udpPort: 1023), 'udpPort'];
        yield 'http port too low' => [self::buildServer(httpPort: 1023), 'httpPort'];
        yield 'session duration not positive' => [self::buildServer(sessionDuration: 0), 'sessionDuration'];
        yield 'blank weather graphics' => [self::buildServer(weatherGraphics: ''), 'weatherGraphics'];
        yield 'ambient temp too low' => [self::buildServer(ambientTemp: -1), 'ambientTemp'];
        yield 'ambient temp too high' => [self::buildServer(ambientTemp: 41), 'ambientTemp'];
        yield 'track temp too high' => [self::buildServer(trackTemp: 56), 'trackTemp'];
        yield 'track grip too high' => [self::buildServer(trackGrip: 101), 'trackGrip'];
    }

    #[DataProvider('valid_boundary_servers')]
    public function test_range_boundaries_are_accepted(Server $server): void
    {
        self::assertCount(0, $this->validator->validate($server));
    }

    /**
     * @return iterable<string, array{Server}>
     */
    public static function valid_boundary_servers(): iterable
    {
        yield 'max clients lower bound' => [self::buildServer(maxClients: 1)];
        yield 'max clients upper bound' => [self::buildServer(maxClients: 24)];
        yield 'tcp port lower bound' => [self::buildServer(tcpPort: 1024)];
        yield 'tcp port upper bound' => [self::buildServer(tcpPort: 65535)];
        yield 'ambient temp lower bound' => [self::buildServer(ambientTemp: 0)];
        yield 'ambient temp upper bound' => [self::buildServer(ambientTemp: 40)];
        yield 'track temp lower bound' => [self::buildServer(trackTemp: 0)];
        yield 'track temp upper bound' => [self::buildServer(trackTemp: 55)];
        yield 'track grip lower bound' => [self::buildServer(trackGrip: 0)];
        yield 'track grip upper bound' => [self::buildServer(trackGrip: 100)];
    }

    /**
     * @param list<string> $cars
     */
    private static function buildServer(
        string $name = 'Spa Endurance',
        string $serverName = 'Pitlane - Spa Endurance',
        string $track = 'spa',
        ?string $trackLayout = null,
        array $cars = ['ks_ferrari_488_gt3'],
        string $password = '',
        string $adminPassword = 'admin-secret',
        int $maxClients = 20,
        int $tcpPort = 9600,
        int $udpPort = 9601,
        int $httpPort = 8081,
        SessionType $sessionType = SessionType::Race,
        int $sessionDuration = 60,
        DurationUnit $durationUnit = DurationUnit::Minutes,
        string $weatherGraphics = '3_clear',
        int $ambientTemp = 22,
        int $trackTemp = 28,
        bool $dynamicTrack = true,
        int $trackGrip = 96,
        bool $tcpNoDelay = true,
        bool $registerToLobby = true,
    ): Server {
        return new Server(
            name: $name,
            serverName: $serverName,
            track: $track,
            trackLayout: $trackLayout,
            cars: $cars,
            password: $password,
            adminPassword: $adminPassword,
            maxClients: $maxClients,
            tcpPort: $tcpPort,
            udpPort: $udpPort,
            httpPort: $httpPort,
            sessionType: $sessionType,
            sessionDuration: $sessionDuration,
            durationUnit: $durationUnit,
            weatherGraphics: $weatherGraphics,
            ambientTemp: $ambientTemp,
            trackTemp: $trackTemp,
            dynamicTrack: $dynamicTrack,
            trackGrip: $trackGrip,
            tcpNoDelay: $tcpNoDelay,
            registerToLobby: $registerToLobby,
        );
    }
}
