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

namespace App\Repository;

use App\Entity\User;
use Doctrine\Persistence\ObjectRepository;
use Symfony\Component\Security\Core\User\PasswordUpgraderInterface;

/**
 * @extends ObjectRepository<User>
 */
interface UserRepositoryInterface extends ObjectRepository, PasswordUpgraderInterface
{
    public function ownerExists(): bool;
}
