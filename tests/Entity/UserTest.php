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
use App\Entity\User;
use App\Enum\DurationUnit;
use App\Enum\SessionType;
use App\Enum\UserRole;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class UserTest extends TestCase
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

    public function test_new_user_exposes_constructor_values(): void
    {
        $user = new User('owner@pitlane.test', UserRole::Owner);

        self::assertNull($user->getId());
        self::assertSame('owner@pitlane.test', $user->getEmail());
        self::assertSame('owner@pitlane.test', $user->getUserIdentifier());
        self::assertSame(UserRole::Owner, $user->getRole());
        self::assertEqualsWithDelta(time(), $user->getCreatedAt()->getTimestamp(), 5);
    }

    public function test_password_can_be_set(): void
    {
        $user = new User('owner@pitlane.test', UserRole::Owner);

        $user->setPassword('hashed-password');

        self::assertSame('hashed-password', $user->getPassword());
    }

    public function test_roles_include_role_specific_and_default_role(): void
    {
        $user = new User('admin@pitlane.test', UserRole::Admin);

        self::assertSame(['ROLE_ADMIN', 'ROLE_USER'], $user->getRoles());
    }

    public function test_new_user_has_no_assigned_servers(): void
    {
        $user = new User('operator@pitlane.test', UserRole::Operator);

        self::assertCount(0, $user->getAssignedServers());
    }

    public function test_owner_has_access_to_any_server_without_assignment(): void
    {
        $user = new User('owner@pitlane.test', UserRole::Owner);

        self::assertTrue($user->hasAccessTo($this->createServer()));
    }

    public function test_admin_has_access_to_any_server_without_assignment(): void
    {
        $user = new User('admin@pitlane.test', UserRole::Admin);

        self::assertTrue($user->hasAccessTo($this->createServer()));
    }

    #[DataProvider('provideFullServerAccessRoles')]
    public function test_has_full_server_access_matches_the_role(UserRole $userRole, bool $expected): void
    {
        $user = new User('user@pitlane.test', $userRole);

        self::assertSame($expected, $user->hasFullServerAccess());
    }

    /**
     * @return iterable<string, array{UserRole, bool}>
     */
    public static function provideFullServerAccessRoles(): iterable
    {
        yield 'owner' => [UserRole::Owner, true];
        yield 'admin' => [UserRole::Admin, true];
        yield 'operator' => [UserRole::Operator, false];
    }

    public function test_operator_has_no_access_to_an_unassigned_server(): void
    {
        $user = new User('operator@pitlane.test', UserRole::Operator);

        self::assertFalse($user->hasAccessTo($this->createServer()));
    }

    public function test_operator_gains_access_after_being_assigned_a_server(): void
    {
        $user = new User('operator@pitlane.test', UserRole::Operator);
        $server = $this->createServer();

        $user->assignServer($server);

        self::assertTrue($user->hasAccessTo($server));
        self::assertCount(1, $user->getAssignedServers());
    }

    public function test_assigning_the_same_server_twice_does_not_duplicate_it(): void
    {
        $user = new User('operator@pitlane.test', UserRole::Operator);
        $server = $this->createServer();

        $user->assignServer($server);
        $user->assignServer($server);

        self::assertCount(1, $user->getAssignedServers());
    }

    public function test_operator_loses_access_after_a_server_is_revoked(): void
    {
        $user = new User('operator@pitlane.test', UserRole::Operator);
        $server = $this->createServer();
        $user->assignServer($server);

        $user->revokeServer($server);

        self::assertFalse($user->hasAccessTo($server));
        self::assertCount(0, $user->getAssignedServers());
    }
}
