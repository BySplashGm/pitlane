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

namespace App\Tests\Security\Voter;

use App\Entity\Server;
use App\Entity\User;
use App\Enum\DurationUnit;
use App\Enum\SessionType;
use App\Enum\UserRole;
use App\Security\Voter\ServerVoter;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;
use Symfony\Component\Security\Core\User\InMemoryUser;

final class ServerVoterTest extends TestCase
{
    #[DataProvider('create_cases')]
    public function test_vote_on_create(UserRole $userRole, int $expected): void
    {
        $serverVoter = new ServerVoter();
        $user = new User('actor@pitlane.test', $userRole);
        $usernamePasswordToken = new UsernamePasswordToken($user, 'main', $user->getRoles());

        self::assertSame($expected, $serverVoter->vote($usernamePasswordToken, null, [ServerVoter::CREATE]));
    }

    /**
     * @return iterable<string, array{UserRole, int}>
     */
    public static function create_cases(): iterable
    {
        yield 'owner can create' => [UserRole::Owner, VoterInterface::ACCESS_GRANTED];
        yield 'admin can create' => [UserRole::Admin, VoterInterface::ACCESS_GRANTED];
        yield 'operator cannot create' => [UserRole::Operator, VoterInterface::ACCESS_DENIED];
    }

    #[DataProvider('delete_cases')]
    public function test_vote_on_delete(UserRole $userRole, bool $assigned, int $expected): void
    {
        $serverVoter = new ServerVoter();
        $user = new User('actor@pitlane.test', $userRole);
        $usernamePasswordToken = new UsernamePasswordToken($user, 'main', $user->getRoles());
        $server = $this->makeServer();

        if ($assigned) {
            $user->assignServer($server);
        }

        self::assertSame($expected, $serverVoter->vote($usernamePasswordToken, $server, [ServerVoter::DELETE]));
    }

    /**
     * @return iterable<string, array{UserRole, bool, int}>
     */
    public static function delete_cases(): iterable
    {
        yield 'owner can delete' => [UserRole::Owner, false, VoterInterface::ACCESS_GRANTED];
        yield 'admin can delete' => [UserRole::Admin, false, VoterInterface::ACCESS_GRANTED];
        yield 'operator cannot delete' => [UserRole::Operator, false, VoterInterface::ACCESS_DENIED];
        // Deletion turns on the role alone, never on assignment: an operator assigned the server is
        // still denied. This case separates DELETE from the assignment-scoped VIEW branch.
        yield 'operator cannot delete even an assigned server' => [UserRole::Operator, true, VoterInterface::ACCESS_DENIED];
    }

    #[DataProvider('view_cases')]
    public function test_vote_on_view(UserRole $userRole, bool $assigned, int $expected): void
    {
        $serverVoter = new ServerVoter();
        $user = new User('actor@pitlane.test', $userRole);
        $usernamePasswordToken = new UsernamePasswordToken($user, 'main', $user->getRoles());
        $server = $this->makeServer();

        if ($assigned) {
            $user->assignServer($server);
        }

        self::assertSame($expected, $serverVoter->vote($usernamePasswordToken, $server, [ServerVoter::VIEW]));
    }

    /**
     * @return iterable<string, array{UserRole, bool, int}>
     */
    public static function view_cases(): iterable
    {
        yield 'owner sees any server' => [UserRole::Owner, false, VoterInterface::ACCESS_GRANTED];
        yield 'admin sees any server' => [UserRole::Admin, false, VoterInterface::ACCESS_GRANTED];
        yield 'operator sees an assigned server' => [UserRole::Operator, true, VoterInterface::ACCESS_GRANTED];
        yield 'operator is denied an unassigned server' => [UserRole::Operator, false, VoterInterface::ACCESS_DENIED];
    }

    public function test_it_abstains_on_unsupported_attribute(): void
    {
        $serverVoter = new ServerVoter();
        $user = new User('actor@pitlane.test', UserRole::Owner);
        $usernamePasswordToken = new UsernamePasswordToken($user, 'main', $user->getRoles());

        self::assertSame(VoterInterface::ACCESS_ABSTAIN, $serverVoter->vote($usernamePasswordToken, null, ['SOME_OTHER_ATTRIBUTE']));
    }

    public function test_it_abstains_on_create_with_a_subject(): void
    {
        $serverVoter = new ServerVoter();
        $user = new User('actor@pitlane.test', UserRole::Owner);
        $usernamePasswordToken = new UsernamePasswordToken($user, 'main', $user->getRoles());

        self::assertSame(VoterInterface::ACCESS_ABSTAIN, $serverVoter->vote($usernamePasswordToken, $this->makeServer(), [ServerVoter::CREATE]));
    }

    public function test_it_abstains_on_view_without_a_server_subject(): void
    {
        $serverVoter = new ServerVoter();
        $user = new User('actor@pitlane.test', UserRole::Owner);
        $usernamePasswordToken = new UsernamePasswordToken($user, 'main', $user->getRoles());

        self::assertSame(VoterInterface::ACCESS_ABSTAIN, $serverVoter->vote($usernamePasswordToken, null, [ServerVoter::VIEW]));
    }

    public function test_it_denies_a_non_application_user(): void
    {
        $serverVoter = new ServerVoter();
        $inMemoryUser = new InMemoryUser('actor@pitlane.test', null, ['ROLE_USER']);
        $usernamePasswordToken = new UsernamePasswordToken($inMemoryUser, 'main', $inMemoryUser->getRoles());

        self::assertSame(VoterInterface::ACCESS_DENIED, $serverVoter->vote($usernamePasswordToken, null, [ServerVoter::CREATE]));
    }

    private function makeServer(): Server
    {
        return new Server(
            'Test Server',
            'Test AC Server',
            'monza',
            null,
            ['ferrari_488_gt3'],
            '',
            'admin-secret',
            24,
            9600,
            9601,
            8081,
            SessionType::Practice,
            30,
            DurationUnit::Minutes,
            '3_clear',
            18,
            26,
            false,
            95,
            true,
            true,
        );
    }
}
