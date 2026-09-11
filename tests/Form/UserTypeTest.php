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

use App\Dto\UserFormData;
use App\Enum\UserRole;
use App\Form\UserType;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use Symfony\Component\Form\ChoiceList\View\ChoiceView;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Test\TypeTestCase;

#[AllowMockObjectsWithoutExpectations]
final class UserTypeTest extends TypeTestCase
{
    /**
     * @return array<string, mixed>
     */
    private function validSubmission(): array
    {
        return [
            'email' => 'new@pitlane.test',
            'plainPassword' => ['first' => 'password', 'second' => 'password'],
            'role' => UserRole::Operator->value,
        ];
    }

    public function test_submitting_valid_data_maps_to_the_dto(): void
    {
        $form = $this->factory->create(UserType::class, null, ['user_id' => null]);

        $form->submit($this->validSubmission());

        self::assertTrue($form->isSynchronized());

        $userFormData = $form->getData();
        self::assertInstanceOf(UserFormData::class, $userFormData);
        self::assertSame('new@pitlane.test', $userFormData->email);
        self::assertSame('password', $userFormData->plainPassword);
        self::assertSame(UserRole::Operator, $userFormData->role);
    }

    public function test_the_password_field_exists_on_create(): void
    {
        $formView = $this->factory->create(UserType::class, null, ['user_id' => null])->createView();

        self::assertArrayHasKey('plainPassword', $formView->children);
    }

    public function test_the_role_choices_never_offer_owner(): void
    {
        $formView = $this->factory->create(UserType::class, null, ['user_id' => null])->createView();

        $roleChoices = $formView['role']->vars['choices'];
        self::assertIsArray($roleChoices);

        $roleValues = [];
        foreach ($roleChoices as $roleChoice) {
            self::assertInstanceOf(ChoiceView::class, $roleChoice);
            $roleValues[] = $roleChoice->value;
        }

        self::assertSame([UserRole::Admin->value, UserRole::Operator->value], $roleValues);
    }

    public function test_the_role_field_is_omitted_when_editing_the_owner(): void
    {
        $form = $this->factory->create(UserType::class, null, ['user_id' => null, 'target_role' => UserRole::Owner]);

        self::assertFalse($form->has('role'));
    }

    public function test_the_role_field_exists_when_editing_a_non_owner(): void
    {
        $form = $this->factory->create(UserType::class, null, ['user_id' => null, 'target_role' => UserRole::Admin]);

        self::assertTrue($form->has('role'));
    }

    public function test_submitting_an_owner_role_is_rejected(): void
    {
        $submission = $this->validSubmission();
        $submission['role'] = UserRole::Owner->value;

        $form = $this->factory->create(UserType::class, null, ['user_id' => null]);
        $form->submit($submission);

        self::assertFalse($form->get('role')->isSynchronized());
    }

    public function test_mismatched_passwords_are_rejected(): void
    {
        $submission = $this->validSubmission();
        $submission['plainPassword'] = ['first' => 'password', 'second' => 'different'];

        $form = $this->factory->create(UserType::class, null, ['user_id' => null]);
        $form->submit($submission);

        self::assertFalse($form->isValid());
        self::assertSame('The password fields must match.', $form->get('plainPassword')->getErrors(true)[0]->getMessage());
    }

    public function test_plain_password_fields_are_rendered_as_password_inputs(): void
    {
        $form = $this->factory->create(UserType::class, null, ['user_id' => null]);

        self::assertInstanceOf(PasswordType::class, $form->get('plainPassword')->get('first')->getConfig()->getType()->getInnerType());
        self::assertInstanceOf(PasswordType::class, $form->get('plainPassword')->get('second')->getConfig()->getType()->getInnerType());
    }

    public function test_plain_password_is_optional_and_unlabelled(): void
    {
        $formView = $this->factory->create(UserType::class, null, ['user_id' => null])->createView();
        $plainPasswordView = $formView->children['plainPassword'];

        self::assertFalse($plainPasswordView->vars['required']);
        self::assertFalse($plainPasswordView->children['first']->vars['required']);
        self::assertFalse($plainPasswordView->children['second']->vars['required']);
        self::assertFalse($plainPasswordView->children['first']->vars['label']);
        self::assertFalse($plainPasswordView->children['second']->vars['label']);
    }
}
