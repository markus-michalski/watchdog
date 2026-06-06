<?php

declare(strict_types=1);

namespace App\Controller;

use App\Enum\CheckStatus;
use App\Repository\CheckResultRepository;
use App\Repository\SiteRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class DashboardController extends AbstractController
{
    #[Route('/', name: 'dashboard')]
    public function index(
        SiteRepository $siteRepository,
        CheckResultRepository $checkResultRepository,
    ): Response {
        $sites = $siteRepository->findAllWithChecks();

        $latestResults = [];
        $siteStatuses = [];

        foreach ($sites as $site) {
            $worst = null;
            foreach ($site->getChecks() as $check) {
                $result = $checkResultRepository->findLatestForCheck($check);
                $latestResults[$check->getId()] = $result;

                if ($result === null || !$check->isActive()) {
                    continue;
                }
                $status = $result->getStatus();
                if ($worst === null || $status->priority() > $worst->priority()) {
                    $worst = $status;
                }
            }
            $siteStatuses[$site->getId()] = $worst;
        }

        return $this->render('dashboard/index.html.twig', [
            'sites' => $sites,
            'latestResults' => $latestResults,
            'siteStatuses' => $siteStatuses,
        ]);
    }
}
