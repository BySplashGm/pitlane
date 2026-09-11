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
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Validation;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class AccountFormDataTest extends TestCase
{
    public function test_it_exposes_deterministic_defaults(): void
    {
        $accountFormData = new AccountFormData();

        self::assertSame('', $accountFormData->email);
        self::assertSame('', $accountFormData->currentPassword);
        self::assertSame('', $accountFormData->newPassword);
    }

    public function test_from_user_maps_the_email(): void
    {
        $user = new User('operator@pitlane.test', UserRole::Operator);

        $accountFormData = AccountFormData::fromUser($user);

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
    private function violations(AccountFormData $accountFormData): array
    {
        $messages = [];
        foreach ($this->validator()->validate($accountFormData) as $constraintViolationList) {
            $messages[] = \sprintf('%s: %s', $constraintViolationList->getPropertyPath(), $constraintViolationList->getMessage());
        }

        return $messages;
    }

    private function validator(): ValidatorInterface
    {
        return Validation::createValidatorBuilder()
            ->enableAttributeMapping()
            ->getValidator();
    }
}
