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

namespace App\Security\Voter;

use App\Entity\User;
use App\Enum\UserRole;
use Override;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * @extends Voter<string, User|null>
 */
final class UserVoter extends Voter
{
    public const string LIST = 'USER_LIST';

    public const string CREATE = 'USER_CREATE';

    public const string EDIT = 'USER_EDIT';

    public const string DELETE = 'USER_DELETE';

    public const string IMPERSONATE = 'USER_IMPERSONATE';

    #[Override]
    protected function supports(string $attribute, mixed $subject): bool
    {
        return match ($attribute) {
            // Listing and creating users have no subject: the decision rests on the actor's role alone.
            self::LIST, self::CREATE => null === $subject,
            // Editing, deleting and impersonating act on a specific user.
            self::EDIT, self::DELETE, self::IMPERSONATE => $subject instanceof User,
            default => false,
        };
    }

    #[Override]
    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        $actor = $token->getUser();

        if (!$actor instanceof User) {
            return false;
        }

        if (\in_array($attribute, [self::LIST, self::CREATE], true)) {
            return $actor->hasFullServerAccess();
        }

        \assert($subject instanceof User);

        // Only the owner may impersonate another account.
        if (self::IMPERSONATE === $attribute) {
            return UserRole::Owner === $actor->getRole();
        }

        // The owner account can never be edited or deleted by anyone but the owner.
        if (UserRole::Owner === $subject->getRole()) {
            return UserRole::Owner === $actor->getRole();
        }

        // Owner and admin can manage every other account, operators cannot manage any.
        return \in_array($actor->getRole(), [UserRole::Owner, UserRole::Admin], true);
    }
}
