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
use App\Repository\ServerRepository;
use App\Security\Voter\ServerVoter;
use App\Service\AcConfigServiceInterface;
use App\Service\DockerServiceInterface;
use App\Service\PortCheckerServiceInterface;
use App\Service\PortConflictServiceInterface;
use RuntimeException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Filesystem\Exception\IOException;
use Symfony\Component\Form\FormError;
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
        private readonly ServerRepository $serverRepository,
        private readonly PortConflictServiceInterface $portConflictService,
        private readonly AcConfigServiceInterface $acConfigService,
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
}
