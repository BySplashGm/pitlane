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
 * Validates password strength: between 12 and 128 characters, drawing from all four character
 * classes (uppercase, lowercase, digit, symbol). The symbol class is a fixed whitelist rather than
 * "any punctuation", so a password can never carry a quote, backslash, angle-bracket or other
 * character that reads as an injection attempt if it later surfaces in a log line or error message;
 * any character outside letters, digits and this whitelist is rejected outright. The upper bound
 * guards the password hasher against an oversized input used as a denial-of-service lever.
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
final class StrongPassword extends Constraint
{
    /**
     * The only symbols accepted, both as the required "symbol" character and anywhere else in the
     * password.
     */
    public const string ALLOWED_SYMBOLS = '!@#$%^&*()-_=+,.?';

    public const int MAX_LENGTH = 128;

    public string $lengthMessage = 'The password must be at least 12 characters long.';

    public string $maxLengthMessage = 'The password cannot be longer than 128 characters.';

    public string $missingUppercaseMessage = 'The password must contain at least one uppercase letter.';

    public string $missingLowercaseMessage = 'The password must contain at least one lowercase letter.';

    public string $missingDigitMessage = 'The password must contain at least one digit.';

    public string $missingSymbolMessage = 'The password must contain at least one of these symbols: {{ symbols }}.';

    public string $disallowedCharacterMessage = 'The password can only contain letters, digits and these symbols: {{ symbols }}.';
}
