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

use App\Dto\UserFormData;
use App\Entity\Server;
use App\Entity\User;
use App\Enum\DurationUnit;
use App\Enum\SessionType;
use App\Enum\UserRole;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use Symfony\Component\Validator\Validation;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class UserFormDataTest extends TestCase
{
    public function test_it_exposes_deterministic_defaults(): void
    {
        $userFormData = new UserFormData();

        self::assertNull($userFormData->userId);
        self::assertSame('', $userFormData->email);
        self::assertSame('', $userFormData->plainPassword);
        self::assertSame(UserRole::Operator, $userFormData->role);
        self::assertNull($userFormData->currentRole);
        self::assertFalse($userFormData->actorIsOwner);
        self::assertSame([], $userFormData->assignedServers);
    }

    public function test_role_choices_never_include_owner(): void
    {
        self::assertSame([UserRole::Admin, UserRole::Operator], new UserFormData()->roleChoices());
    }

    public function test_to_user_builds_a_user_with_the_submitted_email_and_role(): void
    {
        $userFormData = new UserFormData();
        $userFormData->email = 'new@pitlane.test';
        $userFormData->role = UserRole::Admin;

        $user = $userFormData->toUser();

        self::assertSame('new@pitlane.test', $user->getEmail());
        self::assertSame(UserRole::Admin, $user->getRole());
        self::assertSame('', $user->getPassword());
    }

    public function test_from_user_maps_every_field(): void
    {
        $user = new User('operator@pitlane.test', UserRole::Operator);
        new ReflectionProperty(User::class, 'id')->setValue($user, 9);
        $server = $this->makeServer();
        $user->assignServer($server);

        $userFormData = UserFormData::fromUser($user, actorIsOwner: true);

        self::assertSame(9, $userFormData->userId);
        self::assertSame('operator@pitlane.test', $userFormData->email);
        self::assertSame(UserRole::Operator, $userFormData->role);
        self::assertSame(UserRole::Operator, $userFormData->currentRole);
        self::assertTrue($userFormData->actorIsOwner);
        self::assertSame([$server], $userFormData->assignedServers);
    }

    public function test_apply_to_writes_email_and_role_onto_the_existing_user(): void
    {
        $user = new User('old@pitlane.test', UserRole::Operator);

        $userFormData = new UserFormData();
        $userFormData->email = 'renamed@pitlane.test';
        $userFormData->role = UserRole::Admin;

        $userFormData->applyTo($user);

        self::assertSame('renamed@pitlane.test', $user->getEmail());
        self::assertSame(UserRole::Admin, $user->getRole());
    }

    public function test_a_valid_create_password_raises_no_violation(): void
    {
        $userFormData = $this->createFormData();

        self::assertSame([], $this->violations($userFormData));
    }

    public function test_a_blank_password_is_rejected_on_create(): void
    {
        $userFormData = $this->createFormData();
        $userFormData->plainPassword = '';

        self::assertContains('plainPassword: Please enter a password.', $this->violations($userFormData));
    }

    /**
     * Proves the {@see \App\Validator\StrongPassword} attribute is wired up; the exhaustive rule
     * coverage (length, character classes, disallowed characters) lives in
     * {@see \App\Tests\Validator\StrongPasswordValidatorTest}.
     */
    public function test_a_weak_password_is_rejected_on_create(): void
    {
        $userFormData = $this->createFormData();
        $userFormData->plainPassword = 'weakpassword';

        self::assertContains('plainPassword: The password must contain at least one uppercase letter.', $this->violations($userFormData));
    }

    public function test_a_blank_password_raises_no_violation_on_edit(): void
    {
        $userFormData = $this->editFormData();
        $userFormData->plainPassword = '';

        self::assertNotContains('plainPassword: Please enter a password.', $this->violations($userFormData));
    }

    public function test_a_weak_password_is_rejected_on_edit(): void
    {
        $userFormData = $this->editFormData();
        $userFormData->plainPassword = 'weakpassword';

        self::assertContains('plainPassword: The password must contain at least one uppercase letter.', $this->violations($userFormData));
    }

    public function test_owner_can_promote_a_user_to_admin(): void
    {
        $userFormData = $this->createFormData();
        $userFormData->actorIsOwner = true;
        $userFormData->role = UserRole::Admin;

        self::assertNotContains('role: Only the owner can promote a user to admin.', $this->violations($userFormData));
    }

    public function test_admin_cannot_promote_a_user_to_admin(): void
    {
        $userFormData = $this->createFormData();
        $userFormData->actorIsOwner = false;
        $userFormData->role = UserRole::Admin;

        self::assertContains('role: Only the owner can promote a user to admin.', $this->violations($userFormData));
    }

    public function test_admin_can_keep_an_already_admin_account_as_admin(): void
    {
        $userFormData = $this->editFormData();
        $userFormData->actorIsOwner = false;
        $userFormData->currentRole = UserRole::Admin;
        $userFormData->role = UserRole::Admin;

        self::assertNotContains('role: Only the owner can promote a user to admin.', $this->violations($userFormData));
    }

    public function test_admin_can_assign_the_operator_role(): void
    {
        $userFormData = $this->createFormData();
        $userFormData->actorIsOwner = false;
        $userFormData->role = UserRole::Operator;

        self::assertNotContains('role: Only the owner can promote a user to admin.', $this->violations($userFormData));
    }

    private function makeServer(): Server
    {
        return new Server(
            name: 'Spa Endurance',
            serverName: 'Pitlane - Spa Endurance',
            track: 'spa',
            trackLayout: null,
            cars: ['ks_ferrari_488_gt3'],
            password: '',
            adminPassword: 'admin-secret',
            maxClients: 20,
            tcpPort: 9600,
            udpPort: 9601,
            httpPort: 8081,
            sessionType: SessionType::Race,
            sessionDuration: 60,
            durationUnit: DurationUnit::Minutes,
            weatherGraphics: '3_clear',
            ambientTemp: 22,
            trackTemp: 28,
            dynamicTrack: true,
            trackGrip: 96,
            tcpNoDelay: true,
            registerToLobby: true,
        );
    }

    private function createFormData(): UserFormData
    {
        $userFormData = new UserFormData();
        $userFormData->email = 'new@pitlane.test';
        $userFormData->plainPassword = 'Str0ng!Passw0rd';

        return $userFormData;
    }

    /**
     * Like {@see createFormData()} but for an edit: the row carries an id, so a blank password field is
     * left unvalidated (the existing password is kept) while a non-blank one still has to be strong.
     */
    private function editFormData(): UserFormData
    {
        $userFormData = $this->createFormData();
        $userFormData->userId = 7;

        return $userFormData;
    }

    /**
     * @return list<string> every violation as "propertyPath: message"
     */
    private function violations(UserFormData $userFormData): array
    {
        $messages = [];
        foreach ($this->validator()->validate($userFormData) as $constraintViolationList) {
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
