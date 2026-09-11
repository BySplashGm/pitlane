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

final class StrongPasswordValidator extends ConstraintValidator
{
    /**
     * @throws UnexpectedTypeException  when applied through a constraint other than {@see StrongPassword}
     * @throws UnexpectedValueException when the validated value is not a string
     */
    #[Override]
    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof StrongPassword) {
            throw new UnexpectedTypeException($constraint, StrongPassword::class);
        }

        // A blank password is the caller's job: some forms allow it to mean "keep the current one".
        if (null === $value || '' === $value) {
            return;
        }

        if (!\is_string($value)) {
            throw new UnexpectedValueException($value, 'string');
        }

        $quotedSymbols = preg_quote(StrongPassword::ALLOWED_SYMBOLS, '/');

        if (mb_strlen($value) < 12) {
            $this->context->buildViolation($constraint->lengthMessage)->addViolation();
        }

        if (mb_strlen($value) > StrongPassword::MAX_LENGTH) {
            $this->context->buildViolation($constraint->maxLengthMessage)->addViolation();
        }

        if (1 !== preg_match('/[A-Z]/', $value)) {
            $this->context->buildViolation($constraint->missingUppercaseMessage)->addViolation();
        }

        if (1 !== preg_match('/[a-z]/', $value)) {
            $this->context->buildViolation($constraint->missingLowercaseMessage)->addViolation();
        }

        if (1 !== preg_match('/\d/', $value)) {
            $this->context->buildViolation($constraint->missingDigitMessage)->addViolation();
        }

        if (1 !== preg_match(\sprintf('/[%s]/', $quotedSymbols), $value)) {
            $this->context->buildViolation($constraint->missingSymbolMessage)
                ->setParameter('{{ symbols }}', StrongPassword::ALLOWED_SYMBOLS)
                ->addViolation();
        }

        if (1 === preg_match(\sprintf('/[^A-Za-z0-9%s]/', $quotedSymbols), $value)) {
            $this->context->buildViolation($constraint->disallowedCharacterMessage)
                ->setParameter('{{ symbols }}', StrongPassword::ALLOWED_SYMBOLS)
                ->addViolation();
        }
    }
}
