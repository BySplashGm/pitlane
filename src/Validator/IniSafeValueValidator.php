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

use Override;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Exception\UnexpectedValueException;

final class IniSafeValueValidator extends ConstraintValidator
{
    /**
     * Substrings that would break out of a single INI value: the newline pair, the section brackets,
     * both path separators, and the parent-directory token.
     *
     * @var list<string>
     */
    private const array FORBIDDEN_SUBSTRINGS = ["\n", "\r", '[', ']', '/', '\\', '..'];

    /**
     * @throws UnexpectedTypeException  when applied through a constraint other than {@see IniSafeValue}
     * @throws UnexpectedValueException when the validated value is not a string
     */
    #[Override]
    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof IniSafeValue) {
            throw new UnexpectedTypeException($constraint, IniSafeValue::class);
        }

        // A missing optional value is nothing to guard; blankness of required fields is NotBlank's job.
        if (null === $value) {
            return;
        }

        if (!\is_string($value)) {
            throw new UnexpectedValueException($value, 'string');
        }

        $hasForbiddenSubstring = false;
        foreach (self::FORBIDDEN_SUBSTRINGS as $forbiddenSubstring) {
            if (str_contains($value, $forbiddenSubstring)) {
                $hasForbiddenSubstring = true;
            }
        }

        if ($hasForbiddenSubstring || $value !== trim($value)) {
            $this->context->buildViolation($constraint->message)->addViolation();
        }
    }
}
