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

namespace App\Controller;

use App\Dto\AccountFormData;
use App\Entity\User;
use App\Form\AccountType;
use App\Repository\UserRepositoryInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

/**
 * Self-service account settings: any signed-in user editing their own email or password. There is no
 * subject-based access decision here — the acted-on user is always the current session's own account —
 * so this deliberately does not go through a voter.
 */
final class AccountController extends AbstractController
{
    public function __construct(
        private readonly UserRepositoryInterface $userRepository,
        private readonly UserPasswordHasherInterface $userPasswordHasher,
    ) {
    }

    #[Route(path: '/account', name: 'app_account_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, #[CurrentUser] User $user): Response
    {
        $accountFormData = AccountFormData::fromUser($user);

        $form = $this->createForm(AccountType::class, $accountFormData);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if (!$this->userPasswordHasher->isPasswordValid($user, $accountFormData->currentPassword)) {
                $form->get('currentPassword')->addError(new FormError('Current password is incorrect.'));
            } else {
                $accountFormData->applyTo($user);

                if ('' !== $accountFormData->newPassword) {
                    $user->setPassword($this->userPasswordHasher->hashPassword($user, $accountFormData->newPassword));
                }

                $this->userRepository->save($user);

                $this->addFlash('success', 'Account updated.');

                return $this->redirectToRoute('app_account_edit');
            }
        }

        return $this->render('account/edit.html.twig', ['form' => $form]);
    }
}
