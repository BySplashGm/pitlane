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
use App\Repository\ServerRepository;
use App\Service\PortConflictService;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

final class PortConflictServiceTest extends TestCase
{
    public function test_get_used_ports_is_empty_without_servers(): void
    {
        self::assertSame([], $this->makeService()->getUsedPorts());
    }

    public function test_get_used_ports_returns_the_sorted_de_duplicated_union(): void
    {
        // 9601 is shared (server one's udp, server two's tcp) so it must appear only once.
        $portConflictService = $this->makeService(
            $this->makeServer(tcpPort: 9602, udpPort: 9601, httpPort: 8081),
            $this->makeServer(tcpPort: 9601, udpPort: 9603, httpPort: 8080),
        );

        self::assertSame([8080, 8081, 9601, 9602, 9603], $portConflictService->getUsedPorts());
    }

    public function test_has_conflict_is_false_when_every_port_is_free(): void
    {
        $portConflictService = $this->makeService($this->makeServer(tcpPort: 9700, udpPort: 9701, httpPort: 8090));

        self::assertFalse($portConflictService->hasConflict($this->makeServer(tcpPort: 9600, udpPort: 9601, httpPort: 8081)));
    }

    public function test_has_conflict_is_true_when_a_port_collides(): void
    {
        $portConflictService = $this->makeService($this->makeServer(tcpPort: 9600, udpPort: 9701, httpPort: 8090));

        self::assertTrue($portConflictService->hasConflict($this->makeServer(tcpPort: 9600, udpPort: 9601, httpPort: 8081)));
    }

    public function test_get_conflicts_maps_each_taken_port_to_its_owner(): void
    {
        // The subject's udp (9601) collides with the owner's tcp: a cross-slot clash must be caught.
        $server = $this->makeServer(tcpPort: 9601, udpPort: 9700, httpPort: 8081);
        $portConflictService = $this->makeService($server);

        $conflicts = $portConflictService->getConflicts($this->makeServer(tcpPort: 9600, udpPort: 9601, httpPort: 8081));

        self::assertNull($conflicts['tcp']);
        self::assertSame($server, $conflicts['udp']);
        self::assertSame($server, $conflicts['http']);
    }

    public function test_get_conflicts_returns_null_for_every_free_port(): void
    {
        $portConflictService = $this->makeService($this->makeServer(tcpPort: 9700, udpPort: 9701, httpPort: 8090));

        self::assertSame(
            ['tcp' => null, 'udp' => null, 'http' => null],
            $portConflictService->getConflicts($this->makeServer(tcpPort: 9600, udpPort: 9601, httpPort: 8081)),
        );
    }

    public function test_get_conflicts_keeps_the_first_server_that_took_the_port(): void
    {
        $server = $this->makeServer(tcpPort: 9600, udpPort: 9700, httpPort: 8090);
        $secondOwner = $this->makeServer(tcpPort: 9601, udpPort: 9600, httpPort: 8091);
        $portConflictService = $this->makeService($server, $secondOwner);

        $conflicts = $portConflictService->getConflicts($this->makeServer(tcpPort: 9600, udpPort: 9500, httpPort: 8000));

        self::assertSame($server, $conflicts['tcp']);
    }

    public function test_a_persisted_server_does_not_conflict_with_its_own_row_by_identity(): void
    {
        $server = $this->makeServer(tcpPort: 9600, udpPort: 9601, httpPort: 8081, id: 1);
        $portConflictService = $this->makeService($server);

        self::assertFalse($portConflictService->hasConflict($server));
    }

    public function test_a_persisted_server_does_not_conflict_with_its_own_row_by_id(): void
    {
        $server = $this->makeServer(tcpPort: 9600, udpPort: 9601, httpPort: 8081, id: 7);
        $edited = $this->makeServer(tcpPort: 9600, udpPort: 9601, httpPort: 8081, id: 7);
        $portConflictService = $this->makeService($server);

        self::assertFalse($portConflictService->hasConflict($edited));
    }

    public function test_the_subject_is_skipped_by_identity_yet_a_later_server_still_conflicts(): void
    {
        // The subject sits before the clashing server in findAll(): the exclusion must skip only the
        // subject and keep scanning, not stop the whole loop.
        $server = $this->makeServer(tcpPort: 9600, udpPort: 9601, httpPort: 8081, id: 1);
        $later = $this->makeServer(tcpPort: 9600, udpPort: 9700, httpPort: 8090, id: 2);
        $portConflictService = $this->makeService($server, $later);

        self::assertSame($later, $portConflictService->getConflicts($server)['tcp']);
    }

    public function test_the_subject_is_skipped_by_id_yet_a_later_server_still_conflicts(): void
    {
        // Same as above but the subject is excluded by matching id (a detached edit copy), and the
        // clashing server again comes afterwards.
        $server = $this->makeServer(tcpPort: 9600, udpPort: 9601, httpPort: 8081, id: 7);
        $later = $this->makeServer(tcpPort: 9600, udpPort: 9700, httpPort: 8090, id: 8);
        $edited = $this->makeServer(tcpPort: 9600, udpPort: 9601, httpPort: 8081, id: 7);
        $portConflictService = $this->makeService($server, $later);

        self::assertSame($later, $portConflictService->getConflicts($edited)['tcp']);
    }

    public function test_suggest_next_available_ports_defaults_from_9600_on_an_empty_repository(): void
    {
        self::assertSame(
            ['tcp' => 9600, 'udp' => 9601, 'http' => 9602],
            $this->makeService()->suggestNextAvailablePorts(),
        );
    }

    public function test_suggest_next_available_ports_skips_occupied_ports(): void
    {
        $portConflictService = $this->makeService(
            $this->makeServer(tcpPort: 9600, udpPort: 9601, httpPort: 9603),
        );

        self::assertSame(
            ['tcp' => 9602, 'udp' => 9604, 'http' => 9605],
            $portConflictService->suggestNextAvailablePorts(),
        );
    }

    public function test_suggest_next_available_ports_honours_a_custom_start(): void
    {
        self::assertSame(
            ['tcp' => 10000, 'udp' => 10001, 'http' => 10002],
            $this->makeService()->suggestNextAvailablePorts(10000),
        );
    }

    private function makeService(Server ...$servers): PortConflictService
    {
        $serverRepository = self::createStub(ServerRepository::class);
        $serverRepository->method('findAll')->willReturn($servers);

        return new PortConflictService($serverRepository);
    }

    private function makeServer(int $tcpPort, int $udpPort, int $httpPort, ?int $id = null): Server
    {
        $server = new Server(
            name: 'Spa Endurance',
            serverName: 'Pitlane - Spa Endurance',
            track: 'spa',
            trackLayout: null,
            cars: ['ks_ferrari_488_gt3'],
            password: '',
            adminPassword: 'admin-secret',
            maxClients: 20,
            tcpPort: $tcpPort,
            udpPort: $udpPort,
            httpPort: $httpPort,
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

        if (null !== $id) {
            new ReflectionProperty(Server::class, 'id')->setValue($server, $id);
        }

        return $server;
    }
}
