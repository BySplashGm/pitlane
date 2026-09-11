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

use App\Entity\User;
use App\Validator\StrongPassword;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Mutable form model backing the self-service account settings page: a signed-in user editing their
 * own email or password. Whether the current password actually matches is checked by the controller,
 * against the password hasher, not here.
 */
final class AccountFormData
{
    #[Assert\NotBlank]
    #[Assert\Email]
    public string $email = '';

    /**
     * Required to confirm any change: proves whoever holds this session still knows the password.
     */
    #[Assert\NotBlank(message: 'Please enter your current password.')]
    public string $currentPassword = '';

    /**
     * Optional: left blank, the existing password is kept. Strength (length, character classes) is
     * enforced by {@see StrongPassword}, which skips a blank value, so it stays optional here too.
     */
    #[StrongPassword]
    public string $newPassword = '';

    public static function fromUser(User $user): self
    {
        $accountFormData = new self();
        $accountFormData->email = $user->getEmail();

        return $accountFormData;
    }

    /**
     * Writes the validated email back onto the user. The password, if changed, is hashed and set by the
     * controller, which alone holds the password hasher.
     */
    public function applyTo(User $user): void
    {
        $user->setEmail($this->email);
    }
}
