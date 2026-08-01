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
use App\Security\Voter\UserVoter;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use stdClass;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;
use Symfony\Component\Security\Core\User\InMemoryUser;

final class UserVoterTest extends TestCase
{
    #[DataProvider('vote_cases')]
    public function test_vote(UserRole $actorRole, UserRole $subjectRole, string $attribute, int $expected): void
    {
        $userVoter = new UserVoter();
        $actor = new User('actor@pitlane.test', $actorRole);
        $subject = new User('subject@pitlane.test', $subjectRole);
        $usernamePasswordToken = new UsernamePasswordToken($actor, 'main', $actor->getRoles());

        self::assertSame($expected, $userVoter->vote($usernamePasswordToken, $subject, [$attribute]));
    }

    /**
     * @return iterable<string, array{UserRole, UserRole, string, int}>
     */
    public static function vote_cases(): iterable
    {
        yield 'owner can edit owner' => [UserRole::Owner, UserRole::Owner, UserVoter::EDIT, VoterInterface::ACCESS_GRANTED];
        yield 'owner can delete admin' => [UserRole::Owner, UserRole::Admin, UserVoter::DELETE, VoterInterface::ACCESS_GRANTED];
        yield 'admin cannot edit owner' => [UserRole::Admin, UserRole::Owner, UserVoter::EDIT, VoterInterface::ACCESS_DENIED];
        yield 'admin cannot delete owner' => [UserRole::Admin, UserRole::Owner, UserVoter::DELETE, VoterInterface::ACCESS_DENIED];
        yield 'admin can edit operator' => [UserRole::Admin, UserRole::Operator, UserVoter::EDIT, VoterInterface::ACCESS_GRANTED];
        yield 'operator cannot edit admin' => [UserRole::Operator, UserRole::Admin, UserVoter::EDIT, VoterInterface::ACCESS_DENIED];
        yield 'operator cannot edit owner' => [UserRole::Operator, UserRole::Owner, UserVoter::EDIT, VoterInterface::ACCESS_DENIED];
    }

    public function test_it_abstains_on_unsupported_attribute(): void
    {
        $userVoter = new UserVoter();
        $user = new User('actor@pitlane.test', UserRole::Owner);
        $usernamePasswordToken = new UsernamePasswordToken($user, 'main', $user->getRoles());

        self::assertSame(VoterInterface::ACCESS_ABSTAIN, $userVoter->vote($usernamePasswordToken, $user, ['SOME_OTHER_ATTRIBUTE']));
    }

    public function test_it_abstains_on_unsupported_subject(): void
    {
        $userVoter = new UserVoter();
        $user = new User('actor@pitlane.test', UserRole::Owner);
        $usernamePasswordToken = new UsernamePasswordToken($user, 'main', $user->getRoles());

        self::assertSame(VoterInterface::ACCESS_ABSTAIN, $userVoter->vote($usernamePasswordToken, new stdClass(), [UserVoter::EDIT]));
    }

    public function test_it_denies_when_actor_is_not_an_app_user(): void
    {
        $userVoter = new UserVoter();
        $user = new User('subject@pitlane.test', UserRole::Operator);
        $inMemoryUser = new InMemoryUser('system', null, ['ROLE_USER']);
        $usernamePasswordToken = new UsernamePasswordToken($inMemoryUser, 'main', $inMemoryUser->getRoles());

        self::assertSame(VoterInterface::ACCESS_DENIED, $userVoter->vote($usernamePasswordToken, $user, [UserVoter::EDIT]));
    }
}
