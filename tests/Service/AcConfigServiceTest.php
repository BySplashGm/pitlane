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

namespace App\Tests\Service;

use App\Entity\Server;
use App\Enum\DurationUnit;
use App\Enum\SessionType;
use App\Exception\EmptyCarListException;
use App\Exception\MissingContainerSlugException;
use App\Service\AcConfigService;
use Override;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Exception\IOException;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;

final class AcConfigServiceTest extends TestCase
{
    private Filesystem $filesystem;

    private string $serversDir;

    private AcConfigService $acConfigService;

    #[Override]
    protected function setUp(): void
    {
        $this->filesystem = new Filesystem();
        $this->serversDir = Path::join(sys_get_temp_dir(), uniqid('pitlane-ac-config-', true));
        $this->acConfigService = new AcConfigService($this->filesystem, $this->serversDir);
    }

    public function test_get_config_dir_builds_path_from_slug(): void
    {
        $server = $this->createServer();

        self::assertSame(
            Path::join($this->serversDir, 'spa-endurance', 'cfg'),
            $this->acConfigService->getConfigDir($server),
        );
    }

    public function test_get_config_dir_rejects_a_server_without_a_slug(): void
    {
        $server = $this->createServer(withSlug: false);

        $this->expectException(MissingContainerSlugException::class);
        $this->expectExceptionMessage('Server container slug is missing');

        $this->acConfigService->getConfigDir($server);
    }

    /**
     * @throws IOException
     */
    public function test_write_config_rejects_a_server_without_any_car(): void
    {
        $server = $this->createServer();
        $server->setCars([]);

        $this->expectException(EmptyCarListException::class);
        $this->expectExceptionMessage('at least one allowed car');

        $this->acConfigService->writeConfig($server);
    }

    /**
     * @throws IOException
     */
    public function test_write_config_creates_the_directory_and_both_files(): void
    {
        $server = $this->createServer();

        $this->acConfigService->writeConfig($server);

        $configDir = $this->acConfigService->getConfigDir($server);
        self::assertDirectoryExists($configDir);
        self::assertFileExists(Path::join($configDir, 'server_cfg.ini'));
        self::assertFileExists(Path::join($configDir, 'entry_list.ini'));
    }

    /**
     * @throws IOException
     */
    public function test_write_config_renders_the_full_server_cfg(): void
    {
        $server = $this->createServer();

        $this->acConfigService->writeConfig($server);

        $expected = <<<'INI'
            [SERVER]
            NAME=Pitlane - Spa Endurance
            CARS=ks_ferrari_488_gt3;ks_bmw_z4_gt3
            TRACK=spa
            CONFIG_TRACK=
            PASSWORD=
            ADMIN_PASSWORD=admin-secret
            UDP_PORT=9601
            TCP_PORT=9600
            HTTP_PORT=8081
            MAX_CLIENTS=20
            REGISTER_TO_LOBBY=1
            TCP_NODELAY=1

            [RACE]
            NAME=Race
            TIME=60
            IS_OPEN=1

            [DYNAMIC_TRACK]
            SESSION_START=96
            RANDOMNESS=2
            SESSION_TRANSFER=50
            LAP_GAIN=30

            [WEATHER_0]
            GRAPHICS=3_clear
            BASE_TEMPERATURE_AMBIENT=22
            BASE_TEMPERATURE_ROAD=6

            INI;

        self::assertSame($expected, $this->readConfigFile($server, 'server_cfg.ini'));
    }

    /**
     * @throws IOException
     */
    #[DataProvider('sessionSectionProvider')]
    public function test_write_config_renders_the_session_section(
        SessionType $sessionType,
        string $expectedSection,
    ): void {
        $server = $this->createServer();
        $server->setSessionType($sessionType);

        $this->acConfigService->writeConfig($server);

        self::assertStringContainsString($expectedSection, $this->readConfigFile($server, 'server_cfg.ini'));
    }

    /**
     * @return iterable<string, array{SessionType, string}>
     */
    public static function sessionSectionProvider(): iterable
    {
        yield 'practice' => [SessionType::Practice, "[PRACTICE]\nNAME=Practice\nTIME=60\nIS_OPEN=1"];
        yield 'qualify' => [SessionType::Qualify, "[QUALIFY]\nNAME=Qualify\nTIME=60\nIS_OPEN=1"];
        yield 'race' => [SessionType::Race, "[RACE]\nNAME=Race\nTIME=60\nIS_OPEN=1"];
    }

    /**
     * @throws IOException
     */
    #[DataProvider('durationProvider')]
    public function test_write_config_renders_the_duration_key(
        DurationUnit $durationUnit,
        string $expectedLine,
    ): void {
        $server = $this->createServer();
        $server->setDurationUnit($durationUnit);

        $this->acConfigService->writeConfig($server);

        self::assertStringContainsString($expectedLine, $this->readConfigFile($server, 'server_cfg.ini'));
    }

    /**
     * @return iterable<string, array{DurationUnit, string}>
     */
    public static function durationProvider(): iterable
    {
        yield 'minutes' => [DurationUnit::Minutes, "\nTIME=60\nIS_OPEN=1"];
        yield 'laps' => [DurationUnit::Laps, "\nLAPS=60\nIS_OPEN=1"];
    }

    /**
     * @throws IOException
     */
    #[DataProvider('dynamicTrackProvider')]
    public function test_write_config_renders_the_dynamic_track_section(
        bool $dynamicTrack,
        string $expectedSection,
    ): void {
        $server = $this->createServer();
        $server->setDynamicTrack($dynamicTrack);

        $this->acConfigService->writeConfig($server);

        self::assertStringContainsString($expectedSection, $this->readConfigFile($server, 'server_cfg.ini'));
    }

    /**
     * @return iterable<string, array{bool, string}>
     */
    public static function dynamicTrackProvider(): iterable
    {
        yield 'dynamic' => [true, "[DYNAMIC_TRACK]\nSESSION_START=96\nRANDOMNESS=2\nSESSION_TRANSFER=50\nLAP_GAIN=30"];
        yield 'static' => [false, "[DYNAMIC_TRACK]\nSESSION_START=96\nRANDOMNESS=0\nSESSION_TRANSFER=100\nLAP_GAIN=0"];
    }

    /**
     * @throws IOException
     */
    #[DataProvider('weatherProvider')]
    public function test_write_config_renders_the_weather_section(
        int $ambientTemp,
        int $trackTemp,
        int $expectedRoadTemp,
    ): void {
        $server = $this->createServer();
        $server->setAmbientTemp($ambientTemp);
        $server->setTrackTemp($trackTemp);

        $this->acConfigService->writeConfig($server);

        self::assertStringContainsString(
            \sprintf(
                "[WEATHER_0]\nGRAPHICS=3_clear\nBASE_TEMPERATURE_AMBIENT=%d\nBASE_TEMPERATURE_ROAD=%d",
                $ambientTemp,
                $expectedRoadTemp,
            ),
            $this->readConfigFile($server, 'server_cfg.ini'),
        );
    }

    /**
     * @return iterable<string, array{int, int, int}>
     */
    public static function weatherProvider(): iterable
    {
        yield 'road warmer than ambient' => [22, 28, 6];
        yield 'road cooler than ambient' => [30, 25, -5];
    }

    /**
     * @throws IOException
     */
    #[DataProvider('booleanFlagsProvider')]
    public function test_write_config_renders_boolean_flags(
        bool $registerToLobby,
        bool $tcpNoDelay,
        string $expectedRegisterLine,
        string $expectedNoDelayLine,
    ): void {
        $server = $this->createServer();
        $server->setRegisterToLobby($registerToLobby);
        $server->setTcpNoDelay($tcpNoDelay);

        $this->acConfigService->writeConfig($server);

        $serverCfg = $this->readConfigFile($server, 'server_cfg.ini');
        self::assertStringContainsString($expectedRegisterLine, $serverCfg);
        self::assertStringContainsString($expectedNoDelayLine, $serverCfg);
    }

    /**
     * @return iterable<string, array{bool, bool, string, string}>
     */
    public static function booleanFlagsProvider(): iterable
    {
        yield 'register on, nodelay off' => [true, false, "\nREGISTER_TO_LOBBY=1\n", "\nTCP_NODELAY=0\n"];
        yield 'register off, nodelay on' => [false, true, "\nREGISTER_TO_LOBBY=0\n", "\nTCP_NODELAY=1\n"];
    }

    /**
     * @throws IOException
     */
    public function test_write_config_renders_the_track_layout_when_present(): void
    {
        $server = $this->createServer();
        $server->setTrackLayout('gp');

        $this->acConfigService->writeConfig($server);

        self::assertStringContainsString("\nCONFIG_TRACK=gp\n", $this->readConfigFile($server, 'server_cfg.ini'));
    }

    /**
     * @throws IOException
     */
    public function test_write_config_cycles_cars_across_entry_list_slots(): void
    {
        $server = $this->createServer();
        $server->setCars(['ks_ferrari_488_gt3', 'ks_bmw_z4_gt3']);
        $server->setMaxClients(3);

        $this->acConfigService->writeConfig($server);

        $expected = <<<'INI'
            [CAR_0]
            MODEL=ks_ferrari_488_gt3
            SKIN=
            SPECTATOR_MODE=0
            DRIVERNAME=
            TEAM=
            GUID=

            [CAR_1]
            MODEL=ks_bmw_z4_gt3
            SKIN=
            SPECTATOR_MODE=0
            DRIVERNAME=
            TEAM=
            GUID=

            [CAR_2]
            MODEL=ks_ferrari_488_gt3
            SKIN=
            SPECTATOR_MODE=0
            DRIVERNAME=
            TEAM=
            GUID=

            INI;

        self::assertSame($expected, $this->readConfigFile($server, 'entry_list.ini'));
    }

    /**
     * @throws IOException
     */
    public function test_delete_config_removes_the_directory(): void
    {
        $server = $this->createServer();
        $this->acConfigService->writeConfig($server);
        $configDir = $this->acConfigService->getConfigDir($server);
        self::assertDirectoryExists($configDir);

        $this->acConfigService->deleteConfig($server);

        self::assertDirectoryDoesNotExist($configDir);
    }

    #[Override]
    protected function tearDown(): void
    {
        $this->filesystem->remove($this->serversDir);
    }

    private function createServer(bool $withSlug = true): Server
    {
        $server = new Server(
            name: 'Spa Endurance',
            serverName: 'Pitlane - Spa Endurance',
            track: 'spa',
            trackLayout: null,
            cars: ['ks_ferrari_488_gt3', 'ks_bmw_z4_gt3'],
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

        if ($withSlug) {
            $server->generateContainerSlug();
        }

        return $server;
    }

    /**
     * @throws IOException
     */
    private function readConfigFile(Server $server, string $fileName): string
    {
        return $this->filesystem->readFile(Path::join($this->acConfigService->getConfigDir($server), $fileName));
    }
}
