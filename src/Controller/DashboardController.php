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

use App\Entity\User;
use App\Repository\ServerRepositoryInterface;
use App\Service\DockerServiceInterface;
use RuntimeException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

final class DashboardController extends AbstractController
{
    private const string STATUS_RUNNING = 'running';

    private const string STATUS_STOPPED = 'stopped';

    private const string STATUS_UNKNOWN = 'unknown';

    public function __construct(
        private readonly ServerRepositoryInterface $serverRepository,
        private readonly DockerServiceInterface $dockerService,
    ) {
    }

    #[Route(path: '/', name: 'app_dashboard_index', methods: ['GET'])]
    public function index(#[CurrentUser] User $user): Response
    {
        $servers = $user->hasFullServerAccess()
            ? $this->serverRepository->findAllOrderedByName()
            : $this->serverRepository->findAssignedTo($user);

        try {
            $statuses = $this->dockerService->getBulkStatus($servers);
        } catch (RuntimeException) {
            // A Docker daemon hiccup must not take the whole dashboard down: fall back to an
            // unknown status for every server so the page still renders.
            $statuses = [];
        }

        $runningCount = 0;
        $stoppedCount = 0;
        foreach ($servers as $server) {
            $status = $statuses[$server->getId()] ?? self::STATUS_UNKNOWN;

            if (self::STATUS_RUNNING === $status) {
                ++$runningCount;
            } elseif (self::STATUS_STOPPED === $status) {
                ++$stoppedCount;
            }
        }

        return $this->render('dashboard/index.html.twig', [
            'servers' => $servers,
            'statuses' => $statuses,
            'totalCount' => \count($servers),
            'runningCount' => $runningCount,
            'stoppedCount' => $stoppedCount,
        ]);
    }
}
