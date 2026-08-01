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

namespace App\Exception;

use LogicException;

final class EmptyCarListException extends LogicException
{
    public function __construct()
    {
        parent::__construct('Server must have at least one allowed car to build the entry list.');
    }
}
