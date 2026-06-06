<?php

declare(strict_types=1);

namespace App\Controller;

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
        foreach ($sites as $site) {
            foreach ($site->getChecks() as $check) {
                $latestResults[$check->getId()] = $checkResultRepository->findLatestForCheck($check);
            }
        }

        return $this->render('dashboard/index.html.twig', [
            'sites' => $sites,
            'latestResults' => $latestResults,
        ]);
    }
}
