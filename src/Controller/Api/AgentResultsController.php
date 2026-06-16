<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Entity\Agent;
use App\Entity\CheckResult;
use App\Enum\CheckRunner;
use App\Enum\CheckStatus;
use App\Repository\AgentRepository;
use App\Repository\SiteCheckRepository;
use App\Service\AlertService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/v1/agent', name: 'api_agent_')]
final class AgentResultsController
{
    private const MAX_RESULTS_PER_REQUEST = 500;

    public function __construct(
        private readonly AgentRepository $agentRepository,
        private readonly SiteCheckRepository $siteCheckRepository,
        private readonly EntityManagerInterface $em,
        private readonly AlertService $alertService,
    ) {
    }

    #[Route('/results', name: 'results', methods: ['POST'])]
    public function results(Request $request): JsonResponse
    {
        $agent = $this->resolveAgent($request);
        if (null === $agent) {
            return new JsonResponse(['error' => 'Unauthorized'], Response::HTTP_UNAUTHORIZED);
        }

        try {
            $payload = json_decode($request->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return new JsonResponse(['error' => 'Invalid JSON'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        if (!is_array($payload) || !isset($payload['results']) || !is_array($payload['results'])) {
            return new JsonResponse(['error' => 'Invalid payload — expected {"results": [...]}'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        if (count($payload['results']) > self::MAX_RESULTS_PER_REQUEST) {
            return new JsonResponse(
                ['error' => sprintf('Too many results (max %d per request)', self::MAX_RESULTS_PER_REQUEST)],
                Response::HTTP_UNPROCESSABLE_ENTITY,
            );
        }

        $agent->markSeen();

        $accepted = 0;
        $skipped = [];
        $pending = [];

        foreach ($payload['results'] as $item) {
            if (!is_array($item)) {
                continue;
            }

            $checkId = isset($item['site_check_id']) && is_int($item['site_check_id']) ? $item['site_check_id'] : null;
            if (null === $checkId) {
                $skipped[] = ['site_check_id' => null, 'reason' => 'missing site_check_id'];
                continue;
            }

            $check = $this->siteCheckRepository->find($checkId);
            if (null === $check || $check->getRunner() !== CheckRunner::Agent || $check->getAgent()?->getId() !== $agent->getId()) {
                $skipped[] = ['site_check_id' => $checkId, 'reason' => 'not found or not assigned to this agent'];
                continue;
            }

            $statusValue = is_string($item['status'] ?? null) ? $item['status'] : null;
            $status = $statusValue !== null ? CheckStatus::tryFrom($statusValue) : null;
            if (null === $status) {
                $skipped[] = ['site_check_id' => $checkId, 'reason' => 'invalid status'];
                continue;
            }

            $result = new CheckResult();
            $result->setCheck($check);
            $result->setStatus($status);

            if (is_string($item['message'] ?? null)) {
                $result->setMessage(mb_substr($item['message'], 0, 1024));
            }

            if (is_int($item['response_time_ms'] ?? null) && $item['response_time_ms'] >= 0) {
                $result->setResponseTimeMs($item['response_time_ms']);
            }

            if (is_string($item['checked_at'] ?? null)) {
                // Normalize Z → +00:00 since ATOM format does not accept Z
                $dtStr = str_replace('Z', '+00:00', $item['checked_at']);
                $dt = \DateTimeImmutable::createFromFormat(\DateTimeInterface::ATOM, $dtStr);
                if ($dt !== false) {
                    $result->setCheckedAt($dt);
                }
            }

            $this->em->persist($result);
            $pending[] = [$check, $result];
            ++$accepted;
        }

        $this->em->flush();

        foreach ($pending as [$check, $result]) {
            try {
                $this->alertService->evaluate($check, $result);
            } catch (\Throwable) {
                // One failing evaluation must not abort the rest or return a 500 to the agent
            }
        }

        return new JsonResponse([
            'accepted' => $accepted,
            'skipped' => $skipped,
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
