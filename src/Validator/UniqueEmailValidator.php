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

use App\Repository\UserRepositoryInterface;
use Override;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Exception\UnexpectedValueException;

final class UniqueEmailValidator extends ConstraintValidator
{
    public function __construct(
        private readonly UserRepositoryInterface $userRepository,
        private readonly PropertyAccessorInterface $propertyAccessor,
    ) {
    }

    /**
     * @throws UnexpectedTypeException  when applied through a constraint other than {@see UniqueEmail}
     * @throws UnexpectedValueException when the validated value is not an object
     */
    #[Override]
    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof UniqueEmail) {
            throw new UnexpectedTypeException($constraint, UniqueEmail::class);
        }

        if (!\is_object($value)) {
            throw new UnexpectedValueException($value, 'object');
        }

        $email = $this->propertyAccessor->getValue($value, $constraint->emailField);

        if (!\is_string($email) || '' === $email) {
            return;
        }

        $existingUser = $this->userRepository->findOneBy(['email' => $email]);

        if (null === $existingUser) {
            return;
        }

        $excludedUserId = $this->propertyAccessor->getValue($value, $constraint->excludeIdField);

        if ($existingUser->getId() === $excludedUserId) {
            return;
        }

        $this->context->buildViolation($constraint->message)
            ->atPath($constraint->emailField)
            ->addViolation();
    }
}
