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

namespace App\Tests\Form;

use App\Dto\AccountFormData;
use App\Form\AccountType;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Test\TypeTestCase;

#[AllowMockObjectsWithoutExpectations]
final class AccountTypeTest extends TypeTestCase
{
    public function test_submitting_valid_data_maps_to_the_dto(): void
    {
        $form = $this->factory->create(AccountType::class);

        $form->submit([
            'email' => 'renamed@pitlane.test',
            'currentPassword' => 'current-password',
            'newPassword' => ['first' => 'new-password', 'second' => 'new-password'],
        ]);

        self::assertTrue($form->isSynchronized());

        $accountFormData = $form->getData();
        self::assertInstanceOf(AccountFormData::class, $accountFormData);
        self::assertSame('renamed@pitlane.test', $accountFormData->email);
        self::assertSame('current-password', $accountFormData->currentPassword);
        self::assertSame('new-password', $accountFormData->newPassword);
    }

    public function test_submitting_a_blank_new_password_keeps_the_dto_password_empty(): void
    {
        $form = $this->factory->create(AccountType::class);

        $form->submit([
            'email' => 'renamed@pitlane.test',
            'currentPassword' => 'current-password',
            'newPassword' => ['first' => '', 'second' => ''],
        ]);

        self::assertTrue($form->isSynchronized());

        $accountFormData = $form->getData();
        self::assertInstanceOf(AccountFormData::class, $accountFormData);
        self::assertSame('', $accountFormData->newPassword);
    }

    public function test_mismatched_new_passwords_are_rejected(): void
    {
        $form = $this->factory->create(AccountType::class);

        $form->submit([
            'email' => 'renamed@pitlane.test',
            'currentPassword' => 'current-password',
            'newPassword' => ['first' => 'new-password', 'second' => 'different-password'],
        ]);

        self::assertFalse($form->isValid());
        self::assertSame('The password fields must match.', $form->get('newPassword')->getErrors(true)[0]->getMessage());
    }

    public function test_new_password_fields_are_rendered_as_password_inputs(): void
    {
        $form = $this->factory->create(AccountType::class);

        self::assertInstanceOf(PasswordType::class, $form->get('newPassword')->get('first')->getConfig()->getType()->getInnerType());
        self::assertInstanceOf(PasswordType::class, $form->get('newPassword')->get('second')->getConfig()->getType()->getInnerType());
    }

    public function test_new_password_is_optional_and_unlabelled(): void
    {
        $formView = $this->factory->create(AccountType::class)->createView();
        $newPasswordView = $formView->children['newPassword'];

        self::assertFalse($newPasswordView->vars['required']);
        self::assertFalse($newPasswordView->children['first']->vars['required']);
        self::assertFalse($newPasswordView->children['second']->vars['required']);
        self::assertFalse($newPasswordView->children['first']->vars['label']);
        self::assertFalse($newPasswordView->children['second']->vars['label']);
    }
}
