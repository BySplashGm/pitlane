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
use Override;
use Symfony\Component\Validator\Constraint;

/**
 * Class-level constraint checking that {@see $emailField} does not collide with another user's email.
 * Reads {@see $excludeIdField} off the same object so an account keeping its own current email passes,
 * while another account already holding that email does not. Needs the whole object, not just the
 * email value, hence the class target rather than a property one.
 */
#[Attribute(Attribute::TARGET_CLASS)]
final class UniqueEmail extends Constraint
{
    public string $emailField = 'email';

    public string $excludeIdField = 'userId';

    public string $message = 'This email address is already in use.';

    #[Override]
    public function getTargets(): string
    {
        return self::CLASS_CONSTRAINT;
    }
}
