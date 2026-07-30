<?php

declare(strict_types=1);

namespace App\Exception;

use LogicException;

final class MissingContainerSlugException extends LogicException
{
    public function __construct()
    {
        parent::__construct('Server container slug is missing; persist the server or call generateContainerSlug() before generating config paths.');
    }
}
