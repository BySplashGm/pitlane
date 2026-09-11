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

use App\Dto\UserFormData;
use App\Entity\Server;
use App\Entity\User;
use App\Enum\UserRole;
use App\Form\UserType;
use App\Repository\ServerRepositoryInterface;
use App\Repository\UserRepositoryInterface;
use App\Security\Voter\UserVoter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class UserController extends AbstractController
{
    public function __construct(
        private readonly UserRepositoryInterface $userRepository,
        private readonly ServerRepositoryInterface $serverRepository,
        private readonly UserPasswordHasherInterface $userPasswordHasher,
    ) {
    }

    #[Route(path: '/users', name: 'app_user_index', methods: ['GET'])]
    #[IsGranted(UserVoter::LIST)]
    public function index(): Response
    {
        return $this->render('user/index.html.twig', [
            'users' => $this->userRepository->findAllOrderedByEmail(),
        ]);
    }

    #[Route(path: '/users/new', name: 'app_user_new', methods: ['GET', 'POST'])]
    #[IsGranted(UserVoter::CREATE)]
    public function new(Request $request, #[CurrentUser] User $actor): Response
    {
        $userFormData = new UserFormData();
        $userFormData->actorIsOwner = $this->isOwner($actor);

        $form = $this->createForm(UserType::class, $userFormData, ['user_id' => null]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $user = $userFormData->toUser();
            $user->setPassword($this->userPasswordHasher->hashPassword($user, $userFormData->plainPassword));
            $this->userRepository->save($user);

            $this->addFlash('success', \sprintf('User "%s" created.', $user->getEmail()));

            return $this->redirectToRoute('app_user_index');
        }

        return $this->render('user/new.html.twig', ['form' => $form]);
    }

    #[Route(path: '/users/{id}/edit', name: 'app_user_edit', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    #[IsGranted(UserVoter::EDIT, subject: 'user')]
    public function edit(Request $request, User $user, #[CurrentUser] User $actor): Response
    {
        $userFormData = UserFormData::fromUser($user, $this->isOwner($actor));

        $form = $this->createForm(UserType::class, $userFormData, ['user_id' => $user->getId()]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $userFormData->applyTo($user);
            $user->setAssignedServers($userFormData->assignedServers);

            if ('' !== $userFormData->plainPassword) {
                $user->setPassword($this->userPasswordHasher->hashPassword($user, $userFormData->plainPassword));
            }

            $this->userRepository->save($user);

            $this->addFlash('success', \sprintf('User "%s" updated.', $user->getEmail()));

            return $this->redirectToRoute('app_user_index');
        }

        return $this->render('user/edit.html.twig', [
            'form' => $form,
            'user' => $user,
            'availableServers' => array_map(
                static fn (Server $server): array => ['id' => $server->getId(), 'name' => $server->getName()],
                $this->serverRepository->findAllOrderedByName(),
            ),
        ]);
    }

    #[Route(path: '/users/{id}/delete', name: 'app_user_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    #[IsGranted(UserVoter::DELETE, subject: 'user')]
    public function delete(Request $request, User $user): RedirectResponse
    {
        $indexRedirectResponse = $this->redirectToRoute('app_user_index');

        if (!$this->isCsrfTokenValid('user_delete', $request->getPayload()->getString('_csrf_token'))) {
            $this->addFlash('error', 'Invalid CSRF token, please retry.');

            return $indexRedirectResponse;
        }

        $email = $user->getEmail();
        $this->userRepository->remove($user);

        $this->addFlash('success', \sprintf('User "%s" deleted.', $email));

        return $indexRedirectResponse;
    }

    private function isOwner(User $user): bool
    {
        return UserRole::Owner === $user->getRole();
    }
}
