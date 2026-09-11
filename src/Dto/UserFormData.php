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

namespace App\Dto;

use App\Entity\Server;
use App\Entity\User;
use App\Enum\UserRole;
use App\Validator\StrongPassword;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/**
 * Mutable form model for creating or editing a {@see User}.
 *
 * The entity has a required-argument constructor, so it cannot back the form directly: an invalid
 * submit would push nulls into typed setters before validation runs. This DTO carries the submitted
 * values, gets validated in place, then builds or applies to the entity.
 */
final class UserFormData
{
    /**
     * The id of the user being edited, or null when creating.
     */
    public ?int $userId = null;

    #[Assert\NotBlank]
    #[Assert\Email]
    public string $email = '';

    /**
     * Required on create. Optional on edit: an admin resetting another account's password, left blank
     * to keep the existing one. Strength (length, character classes) is enforced by
     * {@see StrongPassword}; a blank value is left to it to skip, so it stays optional here too.
     */
    #[StrongPassword]
    public string $plainPassword = '';

    /**
     * Never Owner: the owner account is created exclusively by the `pitlane:create-owner` console
     * command, so promoting or creating one through the UI is not offered. {@see validateRole()}
     * enforces this rather than an {@see Assert\Choice}, since an owner editing their own account must
     * keep the value the form omits the role field for.
     */
    public UserRole $role = UserRole::Operator;

    /**
     * The role persisted before this edit, or null when creating. Lets {@see validateRole()} allow an
     * already-admin account to be kept as admin by a non-owner actor, while still blocking a new
     * promotion.
     */
    public ?UserRole $currentRole = null;

    /**
     * Whether the acting user is the owner, injected by the controller from the current session. Only
     * the owner may promote a user to admin.
     */
    public bool $actorIsOwner = false;

    /**
     * Edit-only: the servers checked on the assignment checklist.
     *
     * @var list<Server>
     */
    public array $assignedServers = [];

    /**
     * Required on create; a blank submit is rejected here since the plain field carries no constraint
     * of its own (it must stay optional on edit, where a blank submit keeps the existing password).
     */
    #[Assert\Callback]
    public function validatePassword(ExecutionContextInterface $executionContext): void
    {
        if ('' === $this->plainPassword && null === $this->userId) {
            $executionContext->buildViolation('Please enter a password.')
                ->atPath('plainPassword')
                ->addViolation();
        }
    }

    /**
     * The owner's own role is immutable, since {@see UserType} omits the field from that form entirely.
     * For every other account, the role must be one of {@see roleChoices()}, and only the owner can
     * promote a user to admin — an admin keeping an already-admin account's role unchanged is not a
     * promotion, so it is let through.
     */
    #[Assert\Callback]
    public function validateRole(ExecutionContextInterface $executionContext): void
    {
        if (UserRole::Owner === $this->currentRole) {
            if (UserRole::Owner !== $this->role) {
                $executionContext->buildViolation('The owner role cannot be changed.')
                    ->atPath('role')
                    ->addViolation();
            }

            return;
        }

        if (!\in_array($this->role, $this->roleChoices(), true)) {
            $executionContext->buildViolation('Choose a valid role.')
                ->atPath('role')
                ->addViolation();

            return;
        }

        if (UserRole::Admin === $this->role && !$this->actorIsOwner && UserRole::Admin !== $this->currentRole) {
            $executionContext->buildViolation('Only the owner can promote a user to admin.')
                ->atPath('role')
                ->addViolation();
        }
    }

    /**
     * @return list<UserRole>
     */
    public function roleChoices(): array
    {
        return [UserRole::Admin, UserRole::Operator];
    }

    public static function fromUser(User $user, bool $actorIsOwner): self
    {
        $userFormData = new self();

        $userFormData->userId = $user->getId();
        $userFormData->email = $user->getEmail();
        $userFormData->role = $user->getRole();
        $userFormData->currentRole = $user->getRole();
        $userFormData->actorIsOwner = $actorIsOwner;
        $userFormData->assignedServers = array_values($user->getAssignedServers()->toArray());

        return $userFormData;
    }

    /**
     * Builds the entity from the validated form values. The password is set by the controller, which
     * hashes it, keeping the plaintext out of the entity layer.
     */
    public function toUser(): User
    {
        return new User($this->email, $this->role);
    }

    /**
     * Writes the validated form values back onto an existing user, for the edit page. Server assignment
     * is applied by the controller separately, since it only ever happens on edit.
     */
    public function applyTo(User $user): void
    {
        $user
            ->setEmail($this->email)
            ->setRole($this->role);
    }
}
