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

namespace App\Tests\Dto;

use App\Dto\AccountFormData;
use App\Entity\User;
use App\Enum\UserRole;
use App\Repository\UserRepositoryInterface;
use App\Validator\UniqueEmailValidator;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\Validator\ConstraintValidatorFactory;
use Symfony\Component\Validator\Validation;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class AccountFormDataTest extends TestCase
{
    public function test_it_exposes_deterministic_defaults(): void
    {
        $accountFormData = new AccountFormData();

        self::assertNull($accountFormData->userId);
        self::assertSame('', $accountFormData->email);
        self::assertSame('', $accountFormData->currentPassword);
        self::assertSame('', $accountFormData->newPassword);
    }

    public function test_from_user_maps_the_id_and_email(): void
    {
        $user = new User('operator@pitlane.test', UserRole::Operator);
        new ReflectionProperty(User::class, 'id')->setValue($user, 3);

        $accountFormData = AccountFormData::fromUser($user);

        self::assertSame(3, $accountFormData->userId);
        self::assertSame('operator@pitlane.test', $accountFormData->email);
    }

    public function test_apply_to_writes_the_email_onto_the_existing_user(): void
    {
        $user = new User('old@pitlane.test', UserRole::Operator);

        $accountFormData = new AccountFormData();
        $accountFormData->email = 'renamed@pitlane.test';

        $accountFormData->applyTo($user);

        self::assertSame('renamed@pitlane.test', $user->getEmail());
    }

    public function test_a_blank_new_password_raises_no_violation(): void
    {
        $accountFormData = $this->validFormData();
        $accountFormData->newPassword = '';

        self::assertSame([], $this->violations($accountFormData));
    }

    /**
     * Proves the {@see \App\Validator\StrongPassword} attribute is wired up; the exhaustive rule
     * coverage (length, character classes, disallowed characters) lives in
     * {@see \App\Tests\Validator\StrongPasswordValidatorTest}.
     */
    public function test_a_weak_new_password_is_rejected(): void
    {
        $accountFormData = $this->validFormData();
        $accountFormData->newPassword = 'weakpassword';

        self::assertContains('newPassword: The password must contain at least one uppercase letter.', $this->violations($accountFormData));
    }

    public function test_a_strong_new_password_raises_no_violation(): void
    {
        $accountFormData = $this->validFormData();
        $accountFormData->newPassword = 'Str0ng!Passw0rd';

        self::assertSame([], $this->violations($accountFormData));
    }

    public function test_a_blank_current_password_is_rejected(): void
    {
        $accountFormData = $this->validFormData();
        $accountFormData->currentPassword = '';

        self::assertContains('currentPassword: Please enter your current password.', $this->violations($accountFormData));
    }

    /**
     * Proves the {@see \App\Validator\UniqueEmail} attribute is wired up; the exhaustive rule coverage
     * (blank values, the excluded id) lives in {@see \App\Tests\Validator\UniqueEmailValidatorTest}.
     */
    public function test_an_email_already_used_by_another_user_is_rejected(): void
    {
        $existingUser = new User('taken@pitlane.test', UserRole::Operator);
        new ReflectionProperty(User::class, 'id')->setValue($existingUser, 9);

        $userRepository = self::createStub(UserRepositoryInterface::class);
        $userRepository->method('findOneBy')->willReturn($existingUser);

        $accountFormData = $this->validFormData();
        $accountFormData->email = 'taken@pitlane.test';

        self::assertContains('email: This email address is already in use.', $this->violations($accountFormData, $userRepository));
    }

    public function test_keeping_the_current_email_raises_no_violation(): void
    {
        $existingUser = new User('taken@pitlane.test', UserRole::Operator);
        new ReflectionProperty(User::class, 'id')->setValue($existingUser, 3);

        $userRepository = self::createStub(UserRepositoryInterface::class);
        $userRepository->method('findOneBy')->willReturn($existingUser);

        $accountFormData = $this->validFormData();
        $accountFormData->userId = 3;
        $accountFormData->email = 'taken@pitlane.test';

        self::assertNotContains('email: This email address is already in use.', $this->violations($accountFormData, $userRepository));
    }

    private function validFormData(): AccountFormData
    {
        $accountFormData = new AccountFormData();
        $accountFormData->email = 'operator@pitlane.test';
        $accountFormData->currentPassword = 'current-password';

        return $accountFormData;
    }

    /**
     * @return list<string> every violation as "propertyPath: message"
     */
    private function violations(AccountFormData $accountFormData, ?UserRepositoryInterface $userRepository = null): array
    {
        $messages = [];
        foreach ($this->validator($userRepository)->validate($accountFormData) as $constraintViolationList) {
            $messages[] = \sprintf('%s: %s', $constraintViolationList->getPropertyPath(), $constraintViolationList->getMessage());
        }

        return $messages;
    }

    private function validator(?UserRepositoryInterface $userRepository = null): ValidatorInterface
    {
        $userRepository ??= self::createStub(UserRepositoryInterface::class);

        return Validation::createValidatorBuilder()
            ->enableAttributeMapping()
            ->setConstraintValidatorFactory(new ConstraintValidatorFactory([
                UniqueEmailValidator::class => new UniqueEmailValidator($userRepository, PropertyAccess::createPropertyAccessor()),
            ]))
            ->getValidator();
    }
}
