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
 * @extends Voter<string, User>
 */
final class UserVoter extends Voter
{
    public const string EDIT = 'USER_EDIT';

    public const string DELETE = 'USER_DELETE';

    #[Override]
    protected function supports(string $attribute, mixed $subject): bool
    {
        return \in_array($attribute, [self::EDIT, self::DELETE], true) && $subject instanceof User;
    }

    #[Override]
    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        $actor = $token->getUser();

        if (!$actor instanceof User) {
            return false;
        }

        // The owner account can never be edited or deleted by anyone but the owner.
        if (UserRole::Owner === $subject->getRole()) {
            return UserRole::Owner === $actor->getRole();
        }

        // Owner and admin can manage every other account, operators cannot manage any.
        return \in_array($actor->getRole(), [UserRole::Owner, UserRole::Admin], true);
    }
}
