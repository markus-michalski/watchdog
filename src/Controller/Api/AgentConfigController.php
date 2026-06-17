<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Check\CheckRegistry;
use App\Entity\Agent;
use App\Enum\RunnerMode;
use App\Repository\AgentRepository;
use App\Repository\SiteCheckRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/v1/agent', name: 'api_agent_')]
final class AgentConfigController
{
    public function __construct(
        private readonly AgentRepository $agentRepository,
        private readonly SiteCheckRepository $siteCheckRepository,
        private readonly CheckRegistry $checkRegistry,
        private readonly EntityManagerInterface $em,
        private readonly LoggerInterface $logger,
    ) {
    }

    #[Route('/config', name: 'config', methods: ['GET'])]
    public function config(Request $request): JsonResponse
    {
        $agent = $this->resolveAgent($request);
        if (null === $agent) {
            return new JsonResponse(['error' => 'Unauthorized'], Response::HTTP_UNAUTHORIZED);
        }

        $agent->markSeen();
        $this->em->flush();

        $checks = $this->siteCheckRepository->findActiveByAgent($agent);

        $compatible = [];
        foreach ($checks as $check) {
            if (!$this->checkRegistry->has($check->getType()) || $this->checkRegistry->get($check->getType())->runnerMode() === RunnerMode::DashboardOnly) {
                $this->logger->warning('Skipping dashboard-only check type in agent config', [
                    'check_id' => $check->getId(),
                    'type' => $check->getType(),
                    'agent' => $agent->getName(),
                ]);
                continue;
            }
            $compatible[] = $check;
        }

        $payload = array_map(static fn ($check) => [
            'id' => $check->getId(),
            'type' => $check->getType(),
            'config' => $check->getConfig(),
            'check_interval_minutes' => $check->getCheckIntervalMinutes(),
            'run_at_time' => $check->getRunAtTime(),
            'run_now' => $check->isRunNow(),
        ], $compatible);

        // Clear run_now flags after delivering them — agent acknowledged receipt
        foreach ($compatible as $check) {
            if ($check->isRunNow()) {
                $check->setRunNow(false);
            }
        }
        $this->em->flush();

        return new JsonResponse([
            'agent' => [
                'id' => $agent->getId(),
                'name' => $agent->getName(),
            ],
            'checks' => $payload,
        ]);
    }

    private function resolveAgent(Request $request): ?Agent
    {
        $header = $request->headers->get('Authorization', '');
        if (!str_starts_with($header, 'Bearer ')) {
            return null;
        }

        $token = substr($header, 7);
        if ('' === $token) {
            return null;
        }

        return $this->agentRepository->findByToken($token);
    }
}
