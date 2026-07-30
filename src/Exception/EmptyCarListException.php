<?php

declare(strict_types=1);

namespace App\Exception;

use LogicException;

final class EmptyCarListException extends LogicException
{
    public function __construct()
    {
        parent::__construct('Server must have at least one allowed car to build the entry list.');
    }
}
