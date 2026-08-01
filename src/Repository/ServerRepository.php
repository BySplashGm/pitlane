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

use App\Entity\Server;
use Doctrine\Persistence\ObjectRepository;

/**
 * @extends ObjectRepository<Server>
 */
interface ServerRepository extends ObjectRepository
{
    public function findBySlug(string $containerSlug): ?Server;
}
