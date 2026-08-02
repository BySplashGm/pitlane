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

use App\Entity\User;
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

    public function test_it_abstains_on_unsupported_attribute(): void
    {
        $serverVoter = new ServerVoter();
        $user = new User('actor@pitlane.test', UserRole::Owner);
        $usernamePasswordToken = new UsernamePasswordToken($user, 'main', $user->getRoles());

        self::assertSame(VoterInterface::ACCESS_ABSTAIN, $serverVoter->vote($usernamePasswordToken, null, ['SOME_OTHER_ATTRIBUTE']));
    }

    public function test_it_denies_a_non_application_user(): void
    {
        $serverVoter = new ServerVoter();
        $inMemoryUser = new InMemoryUser('actor@pitlane.test', null, ['ROLE_USER']);
        $usernamePasswordToken = new UsernamePasswordToken($inMemoryUser, 'main', $inMemoryUser->getRoles());

        self::assertSame(VoterInterface::ACCESS_DENIED, $serverVoter->vote($usernamePasswordToken, null, [ServerVoter::CREATE]));
    }
}
