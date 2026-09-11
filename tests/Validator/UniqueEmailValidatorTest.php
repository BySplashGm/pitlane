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

namespace App\Tests\Validator;

use App\Dto\UserFormData;
use App\Entity\User;
use App\Enum\UserRole;
use App\Repository\UserRepositoryInterface;
use App\Validator\UniqueEmail;
use App\Validator\UniqueEmailValidator;
use Override;
use ReflectionProperty;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\ConstraintValidatorInterface;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Exception\UnexpectedValueException;
use Symfony\Component\Validator\Test\ConstraintValidatorTestCase;

/**
 * @extends ConstraintValidatorTestCase<UniqueEmailValidator>
 */
final class UniqueEmailValidatorTest extends ConstraintValidatorTestCase
{
    /**
     * The email the stubbed repository reports as already taken; null means every email is free.
     */
    private ?string $existingEmail = null;

    /**
     * The id carried by the user the stubbed repository returns for {@see $existingEmail}.
     */
    private int $existingUserId = 5;

    public function test_no_matching_user_raises_no_violation(): void
    {
        $this->validator->validate($this->formData('new@pitlane.test', userId: null), new UniqueEmail());

        $this->assertNoViolation();
    }

    public function test_a_blank_email_raises_no_violation_without_querying_the_repository(): void
    {
        $userRepository = $this->createMock(UserRepositoryInterface::class);
        $userRepository->expects(self::never())->method('findOneBy');

        $uniqueEmailValidator = new UniqueEmailValidator($userRepository, PropertyAccess::createPropertyAccessor());
        $uniqueEmailValidator->validateInContext($this->formData('', userId: null), new UniqueEmail(), $this->context);

        $this->assertNoViolation();
    }

    public function test_a_match_belonging_to_the_excluded_id_raises_no_violation(): void
    {
        $this->existingEmail = 'mine@pitlane.test';

        $this->validator->validate($this->formData('mine@pitlane.test', userId: $this->existingUserId), new UniqueEmail());

        $this->assertNoViolation();
    }

    public function test_a_match_belonging_to_another_id_is_rejected(): void
    {
        $this->existingEmail = 'taken@pitlane.test';

        $this->validator->validate($this->formData('taken@pitlane.test', userId: null), new UniqueEmail());

        $this->buildViolation('This email address is already in use.')
            ->atPath('property.path.email')
            ->assertRaised();
    }

    public function test_it_rejects_a_foreign_constraint(): void
    {
        $this->expectException(UnexpectedTypeException::class);

        $this->validator->validate($this->formData('new@pitlane.test', userId: null), new NotBlank());
    }

    public function test_it_rejects_a_non_object_value(): void
    {
        $this->expectException(UnexpectedValueException::class);

        $this->validator->validate('not-an-object', new UniqueEmail());
    }

    #[Override]
    protected function createValidator(): ConstraintValidatorInterface
    {
        $userRepository = self::createStub(UserRepositoryInterface::class);
        $userRepository->method('findOneBy')->willReturnCallback($this->findOneByEmail(...));

        return new UniqueEmailValidator($userRepository, PropertyAccess::createPropertyAccessor());
    }

    private function formData(string $email, ?int $userId): UserFormData
    {
        $userFormData = new UserFormData();
        $userFormData->email = $email;
        $userFormData->userId = $userId;

        return $userFormData;
    }

    private function userWithId(int $id): User
    {
        $user = new User('existing@pitlane.test', UserRole::Operator);
        new ReflectionProperty(User::class, 'id')->setValue($user, $id);

        return $user;
    }

    /**
     * @param array<string, mixed> $criteria
     */
    private function findOneByEmail(array $criteria): ?User
    {
        return $criteria['email'] === $this->existingEmail ? $this->userWithId($this->existingUserId) : null;
    }
}
