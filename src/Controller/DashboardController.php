<?php

declare(strict_types=1);

namespace App\Controller;

use App\Repository\CheckResultRepository;
use App\Repository\ClientRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class DashboardController extends AbstractController
{
    #[Route('/', name: 'dashboard')]
    public function index(
        ClientRepository $clientRepository,
        CheckResultRepository $checkResultRepository,
    ): Response {
        $clients = $clientRepository->findAllWithChecks();

        $latestResults = [];
        $clientStatuses = [];

        foreach ($clients as $client) {
            $worst = null;
            foreach ($client->getChecks() as $check) {
                $result = $checkResultRepository->findLatestForCheck($check);
                $latestResults[$check->getId()] = $result;

                if (null === $result || !$check->isActive()) {
                    continue;
                }
                $status = $result->getStatus();
                if (null === $worst || $status->priority() > $worst->priority()) {
                    $worst = $status;
                }
            }
            $clientStatuses[$client->getId()] = $worst;
        }

        return $this->render('dashboard/index.html.twig', [
            'clients' => $clients,
            'latestResults' => $latestResults,
            'clientStatuses' => $clientStatuses,
        ]);
    }
}
