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

use App\Entity\Server;
use App\Entity\User;
use Override;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * @extends Voter<string, Server|null>
 */
final class ServerVoter extends Voter
{
    public const string CREATE = 'SERVER_CREATE';

    public const string VIEW = 'SERVER_VIEW';

    public const string EDIT = 'SERVER_EDIT';

    public const string DELETE = 'SERVER_DELETE';

    #[Override]
    protected function supports(string $attribute, mixed $subject): bool
    {
        return match ($attribute) {
            // Creating a server has no subject yet: the decision rests on the actor's role alone.
            self::CREATE => null === $subject,
            // Viewing, editing and deleting act on a specific server.
            self::VIEW, self::EDIT, self::DELETE => $subject instanceof Server,
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

        return match ($attribute) {
            // Creating, editing and deleting a server rest on the actor's role alone: owner and admin
            // only. Editing settings is denied to operators even for a server assigned to them.
            self::CREATE, self::EDIT, self::DELETE => $actor->hasFullServerAccess(),
            // The only remaining supported attribute is VIEW, scoped per assignment: owner and admin
            // see every server, operators only the ones assigned to them.
            default => $subject instanceof Server && $actor->hasAccessTo($subject),
        };
    }
}
