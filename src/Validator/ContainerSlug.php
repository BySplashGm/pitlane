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

namespace App\Validator;

use Attribute;
use Symfony\Component\Validator\Constraint;

/**
 * Validates the container slug derived from a server name: it must be non-empty once slugified,
 * not clash with a reserved platform name, and be unique across existing servers.
 *
 * The slug (not the raw name) is the unique Docker container identifier, so two names that slugify
 * the same collide; validating here turns that into a form error instead of a database-level failure.
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
final class ContainerSlug extends Constraint
{
    public string $emptyMessage = 'This name must contain at least one letter or number.';

    public string $reservedMessage = 'This name is reserved by the platform and cannot be used.';

    public string $takenMessage = 'A server with a similar name already exists.';
}
