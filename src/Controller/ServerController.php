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
use App\Exception\EmptyCarListException;
use App\Exception\MissingContainerSlugException;
use App\Form\ServerType;
use App\Repository\ServerRepository;
use App\Security\Voter\ServerVoter;
use App\Service\AcConfigServiceInterface;
use App\Service\PortConflictServiceInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Filesystem\Exception\IOException;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class ServerController extends AbstractController
{
    public function __construct(
        private readonly ServerRepository $serverRepository,
        private readonly PortConflictServiceInterface $portConflictService,
        private readonly AcConfigServiceInterface $acConfigService,
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

                // TODO: redirect to the server detail page once app_server_show exists (see issue #16).
                return $this->redirectToRoute('app_dashboard_index');
            }
        }

        return $this->render('server/new.html.twig', [
            'form' => $form,
        ]);
    }
}
