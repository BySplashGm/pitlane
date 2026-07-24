<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\User;
use Doctrine\Persistence\ObjectRepository;
use Symfony\Component\Security\Core\User\PasswordUpgraderInterface;

/**
 * @extends ObjectRepository<User>
 */
interface UserRepository extends ObjectRepository, PasswordUpgraderInterface
{
    public function ownerExists(): bool;
}
