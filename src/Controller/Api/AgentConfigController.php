<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Entity\Agent;
use App\Repository\AgentRepository;
use App\Repository\SiteCheckRepository;
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
    ) {
    }

    #[Route('/config', name: 'config', methods: ['GET'])]
    public function config(Request $request): JsonResponse
    {
        $agent = $this->resolveAgent($request);
        if (null === $agent) {
            return new JsonResponse(['error' => 'Unauthorized'], Response::HTTP_UNAUTHORIZED);
        }

        $checks = $this->siteCheckRepository->findActiveByAgent($agent);

        return new JsonResponse([
            'agent' => [
                'id' => $agent->getId(),
                'name' => $agent->getName(),
            ],
            'checks' => array_map(static fn ($check) => [
                'id' => $check->getId(),
                'type' => $check->getType(),
                'config' => $check->getConfig(),
                'check_interval_minutes' => $check->getCheckIntervalMinutes(),
                'run_at_time' => $check->getRunAtTime(),
            ], $checks),
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
