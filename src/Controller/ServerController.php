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

use App\Dto\ServerFormData;
use App\Entity\Server;
use App\Exception\EmptyCarListException;
use App\Exception\MissingContainerSlugException;
use App\Form\ServerType;
use App\Repository\ServerRepositoryInterface;
use App\Security\Voter\ServerVoter;
use App\Service\AcConfigServiceInterface;
use App\Service\AcContentServiceInterface;
use App\Service\DockerServiceInterface;
use App\Service\PortCheckerServiceInterface;
use App\Service\PortConflictServiceInterface;
use RuntimeException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Filesystem\Exception\IOException;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class ServerController extends AbstractController
{
    /**
     * Fallback status when the Docker daemon cannot be reached: the page still renders.
     */
    private const string STATUS_UNKNOWN = 'unknown';

    public function __construct(
        private readonly ServerRepositoryInterface $serverRepository,
        private readonly PortConflictServiceInterface $portConflictService,
        private readonly AcConfigServiceInterface $acConfigService,
        private readonly AcContentServiceInterface $acContentService,
        private readonly DockerServiceInterface $dockerService,
        private readonly PortCheckerServiceInterface $portCheckerService,
    ) {
    }

    /**
     * @throws IOException
     * @throws MissingContainerSlugException
     * @throws EmptyCarListException
     */
    #[Route(path: '/server/new', name: 'app_server_new', methods: ['GET', 'POST'])]
    #[IsGranted(ServerVoter::CREATE)]
    public function new(Request $request): Response
    {
        $serverFormData = new ServerFormData();

        $suggestedPorts = $this->portConflictService->suggestNextAvailablePorts();
        $serverFormData->tcpPort = $suggestedPorts['tcp'];
        $serverFormData->udpPort = $suggestedPorts['udp'];
        $serverFormData->httpPort = $suggestedPorts['http'];

        // Feed the server-side Assert\Choice callbacks: membership is validated against the installed
        // content, so a forged POST cannot smuggle an unlisted value past the UI dropdowns.
        $serverFormData->availableTracks = $this->acContentService->tracks();
        $serverFormData->availableCars = $this->acContentService->cars();
        $serverFormData->availableWeatherGraphics = $this->acContentService->weather();

        $form = $this->createForm(ServerType::class, $serverFormData);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $server = $serverFormData->toServer();

            if ($this->portConflictService->hasConflict($server)) {
                $form->addError(new FormError('One or more of the chosen ports is already used by another server.'));
            } else {
                $this->serverRepository->save($server);
                $this->acConfigService->writeConfig($server);

                $this->addFlash('success', \sprintf('Server "%s" created.', $server->getName()));

                return $this->redirectToRoute('app_server_show', ['id' => $server->getId()]);
            }
        }

        return $this->render('server/new.html.twig', [
            'form' => $form,
            'availableCars' => $serverFormData->availableCars,
        ]);
    }

    /**
     * @throws IOException
     * @throws MissingContainerSlugException
     * @throws EmptyCarListException
     */
    #[Route(path: '/server/{id}/edit', name: 'app_server_edit', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    #[IsGranted(ServerVoter::EDIT, subject: 'server')]
    public function edit(Request $request, Server $server): Response
    {
        $serverFormData = ServerFormData::fromServer($server);

        // Feed the server-side Assert\Choice callbacks: membership is validated against the installed
        // content, so a forged POST cannot smuggle an unlisted value past the UI dropdowns.
        $serverFormData->availableTracks = $this->acContentService->tracks();
        $serverFormData->availableCars = $this->acContentService->cars();
        $serverFormData->availableWeatherGraphics = $this->acContentService->weather();

        $form = $this->createForm(ServerType::class, $serverFormData);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $serverFormData->applyTo($server);

            // The port check excludes this server's own persisted row by id, so keeping its ports is
            // not a false clash.
            if ($this->portConflictService->hasConflict($server)) {
                $form->addError(new FormError('One or more of the chosen ports is already used by another server.'));
            } else {
                $this->serverRepository->save($server);
                $this->acConfigService->writeConfig($server);

                $this->addFlash('success', \sprintf('Server "%s" updated.', $server->getName()));

                return $this->redirectToRoute('app_server_show', ['id' => $server->getId()]);
            }
        }

        return $this->render('server/edit.html.twig', [
            'form' => $form,
            'server' => $server,
            'availableCars' => $serverFormData->availableCars,
            'running' => $this->isRunning($server),
        ]);
    }

    #[Route(path: '/server/{id}', name: 'app_server_show', requirements: ['id' => '\d+'], methods: ['GET'])]
    #[IsGranted(ServerVoter::VIEW, subject: 'server')]
    public function show(Server $server): Response
    {
        try {
            $status = $this->dockerService->getContainerStatus($server);
        } catch (RuntimeException|MissingContainerSlugException) {
            // A Docker daemon hiccup must not blank the page: fall back to an unknown status.
            $status = self::STATUS_UNKNOWN;
        }

        try {
            $ports = $this->portCheckerService->checkServer($server);
        } catch (RuntimeException) {
            // The public IP could not be resolved: report every port as not-checkable rather than fail.
            $ports = ['tcp' => null, 'udp' => null, 'http' => null];
        }

        return $this->render('server/show.html.twig', [
            'server' => $server,
            'status' => $status,
            'ports' => $ports,
        ]);
    }

    #[Route(path: '/server/{id}/logs', name: 'app_server_logs', requirements: ['id' => '\d+'], methods: ['GET'])]
    #[IsGranted(ServerVoter::VIEW, subject: 'server')]
    public function logs(Server $server): Response
    {
        try {
            $logs = $this->dockerService->getLogs($server);
        } catch (RuntimeException|MissingContainerSlugException) {
            // The poller hits this endpoint every few seconds: degrade quietly instead of 500-looping.
            $logs = '';
        }

        // Logs are untrusted server output: text/plain plus nosniff so a direct hit on this URL can
        // never be MIME-sniffed into executable HTML.
        return new Response($logs, Response::HTTP_OK, [
            'Content-Type' => 'text/plain; charset=utf-8',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    #[Route(path: '/server/{id}/start', name: 'app_server_start', requirements: ['id' => '\d+'], methods: ['POST'])]
    #[IsGranted(ServerVoter::CONTROL, subject: 'server')]
    public function start(Request $request, Server $server): RedirectResponse
    {
        return $this->control(
            $request,
            $server,
            'start',
            $this->dockerService->startServer(...),
            \sprintf('Server "%s" started.', $server->getName()),
            \sprintf('Could not start server "%s".', $server->getName()),
        );
    }

    #[Route(path: '/server/{id}/stop', name: 'app_server_stop', requirements: ['id' => '\d+'], methods: ['POST'])]
    #[IsGranted(ServerVoter::CONTROL, subject: 'server')]
    public function stop(Request $request, Server $server): RedirectResponse
    {
        return $this->control(
            $request,
            $server,
            'stop',
            $this->dockerService->stopServer(...),
            \sprintf('Server "%s" stopped.', $server->getName()),
            \sprintf('Could not stop server "%s".', $server->getName()),
        );
    }

    #[Route(path: '/server/{id}/restart', name: 'app_server_restart', requirements: ['id' => '\d+'], methods: ['POST'])]
    #[IsGranted(ServerVoter::CONTROL, subject: 'server')]
    public function restart(Request $request, Server $server): RedirectResponse
    {
        return $this->control(
            $request,
            $server,
            'restart',
            $this->dockerService->restartServer(...),
            \sprintf('Server "%s" restarted.', $server->getName()),
            \sprintf('Could not restart server "%s".', $server->getName()),
        );
    }

    /**
     * Shared body for the start/stop/restart actions: rejects a bad CSRF token, runs the Docker
     * operation, and turns success or any Docker/config failure into a flash message. Always lands
     * back on the server detail page so an operational error is shown, never surfaced as a 500.
     *
     * @param callable(Server): void $operation
     */
    private function control(
        Request $request,
        Server $server,
        string $action,
        callable $operation,
        string $successMessage,
        string $errorMessage,
    ): RedirectResponse {
        $redirectResponse = $this->redirectToRoute('app_server_show', ['id' => $server->getId()]);

        if (!$this->isCsrfTokenValid(\sprintf('server_%s', $action), $request->getPayload()->getString('_csrf_token'))) {
            $this->addFlash('error', 'Invalid CSRF token, please retry.');

            return $redirectResponse;
        }

        try {
            $operation($server);
            $this->addFlash('success', $successMessage);
        } catch (RuntimeException|MissingContainerSlugException $exception) {
            // A Docker daemon hiccup or a server with no container yet is an operational failure, not a
            // bug: report it — with the underlying reason so it can be diagnosed — as a flash and keep
            // the user on the detail page.
            $this->addFlash('error', \sprintf('%s %s', $errorMessage, $exception->getMessage()));
        }

        return $redirectResponse;
    }

    /**
     * Whether the server's container is currently running. A Docker daemon hiccup, or a server with no
     * container yet, degrades to false: the edit page still renders, just without the restart warning.
     */
    private function isRunning(Server $server): bool
    {
        try {
            return 'running' === $this->dockerService->getContainerStatus($server);
        } catch (RuntimeException|MissingContainerSlugException) {
            return false;
        }
    }
}
