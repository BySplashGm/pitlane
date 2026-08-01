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
use Override;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * @extends Voter<string, null>
 */
final class ServerVoter extends Voter
{
    public const string CREATE = 'SERVER_CREATE';

    #[Override]
    protected function supports(string $attribute, mixed $subject): bool
    {
        // Creating a server has no subject yet: the decision rests on the actor's role alone.
        return self::CREATE === $attribute;
    }

    #[Override]
    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        $actor = $token->getUser();

        if (!$actor instanceof User) {
            return false;
        }

        // Owner and admin may create servers; operators may not.
        return $actor->hasFullServerAccess();
    }
}
