<?php

declare(strict_types=1);

namespace App\Tests\Unit\Controller\Api;

use App\Controller\Api\AgentResultsController;
use App\Entity\Agent;
use App\Entity\AlertState;
use App\Entity\CheckResult;
use App\Entity\SiteCheck;
use App\Enum\CheckRunner;
use App\Repository\AgentRepository;
use App\Repository\AlertStateRepository;
use App\Repository\SiteCheckRepository;
use App\Service\AlertService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

#[AllowMockObjectsWithoutExpectations]
class AgentResultsControllerTest extends TestCase
{
    private AgentRepository&MockObject $agentRepository;
    private SiteCheckRepository&MockObject $siteCheckRepository;
    private EntityManagerInterface&MockObject $em;
    private AlertStateRepository&MockObject $alertStateRepository;
    private MessageBusInterface&MockObject $bus;
    private AgentResultsController $controller;

    protected function setUp(): void
    {
        $this->agentRepository = $this->createMock(AgentRepository::class);
        $this->siteCheckRepository = $this->createMock(SiteCheckRepository::class);
        $this->em = $this->createMock(EntityManagerInterface::class);
        $this->alertStateRepository = $this->createMock(AlertStateRepository::class);
        $this->bus = $this->createMock(MessageBusInterface::class);
        $this->bus->method('dispatch')->willReturn(new Envelope(new \stdClass()));

        $this->controller = new AgentResultsController(
            $this->agentRepository,
            $this->siteCheckRepository,
            $this->em,
            $this->buildAlertService(),
        );
    }

    private function buildAlertService(): AlertService
    {
        return new AlertService($this->alertStateRepository, $this->em, $this->bus);
    }

    #[Test]
    public function returnsUnauthorizedWhenNoAuthHeader(): void
    {
        $request = Request::create('/api/v1/agent/results', 'POST');

        $this->agentRepository->expects($this->never())->method('findByToken');

        $response = $this->controller->results($request);

        $this->assertSame(401, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('error', $data);
    }

    #[Test]
    public function returnsUnauthorizedWhenTokenNotFound(): void
    {
        $request = Request::create('/api/v1/agent/results', 'POST', [], [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer invalid-token',
        ]);

        $this->agentRepository->expects($this->once())
            ->method('findByToken')
            ->with('invalid-token')
            ->willReturn(null);

        $response = $this->controller->results($request);

        $this->assertSame(401, $response->getStatusCode());
    }

    #[Test]
    public function returnsUnprocessableWhenPayloadMissingResultsKey(): void
    {
        $agent = $this->buildAgent();
        $request = $this->buildRequest($agent->getId(), '{"foo": "bar"}');

        $this->agentRepository->method('findByToken')->willReturn($agent);

        $response = $this->controller->results($request);

        $this->assertSame(422, $response->getStatusCode());
    }

    #[Test]
    public function returnsUnprocessableWhenPayloadIsNotJson(): void
    {
        $agent = $this->buildAgent();
        $request = $this->buildRequest($agent->getId(), 'not-json');

        $this->agentRepository->method('findByToken')->willReturn($agent);

        $response = $this->controller->results($request);

        $this->assertSame(422, $response->getStatusCode());
    }

    #[Test]
    public function marksAgentSeenAndPersistsOnSuccess(): void
    {
        $agent = $this->buildAgent();
        $check = $this->buildCheck($agent);

        $this->agentRepository->method('findByToken')->willReturn($agent);
        $this->siteCheckRepository->method('find')->with(1)->willReturn($check);

        $this->em->expects($this->once())->method('persist');
        $this->em->expects($this->atLeast(1))->method('flush');
        $this->stubAlertState($check);

        $payload = json_encode(['results' => [
            ['site_check_id' => 1, 'status' => 'ok', 'message' => 'All good', 'response_time_ms' => 12],
        ]]);
        $request = $this->buildRequest($agent->getId(), $payload);

        $response = $this->controller->results($request);

        $this->assertSame(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertSame(1, $data['accepted']);
        $this->assertSame([], $data['skipped']);
    }

    #[Test]
    public function skipsCheckNotAssignedToAgent(): void
    {
        $agent = $this->buildAgent();
        $otherAgent = $this->buildAgent(99);
        $check = $this->buildCheck($otherAgent);

        $this->agentRepository->method('findByToken')->willReturn($agent);
        $this->siteCheckRepository->method('find')->with(1)->willReturn($check);

        $payload = json_encode(['results' => [
            ['site_check_id' => 1, 'status' => 'ok'],
        ]]);
        $request = $this->buildRequest($agent->getId(), $payload);

        $response = $this->controller->results($request);
        $data = json_decode($response->getContent(), true);

        $this->assertSame(0, $data['accepted']);
        $this->assertCount(1, $data['skipped']);
    }

    #[Test]
    public function skipsDashboardCheck(): void
    {
        $agent = $this->buildAgent();
        $check = $this->buildCheck($agent, CheckRunner::Dashboard);

        $this->agentRepository->method('findByToken')->willReturn($agent);
        $this->siteCheckRepository->method('find')->with(1)->willReturn($check);

        $payload = json_encode(['results' => [
            ['site_check_id' => 1, 'status' => 'ok'],
        ]]);
        $request = $this->buildRequest($agent->getId(), $payload);

        $response = $this->controller->results($request);
        $data = json_decode($response->getContent(), true);

        $this->assertSame(0, $data['accepted']);
        $this->assertCount(1, $data['skipped']);
    }

    #[Test]
    public function skipsItemWithInvalidStatus(): void
    {
        $agent = $this->buildAgent();
        $check = $this->buildCheck($agent);

        $this->agentRepository->method('findByToken')->willReturn($agent);
        $this->siteCheckRepository->method('find')->with(1)->willReturn($check);

        $payload = json_encode(['results' => [
            ['site_check_id' => 1, 'status' => 'invalid-value'],
        ]]);
        $request = $this->buildRequest($agent->getId(), $payload);

        $response = $this->controller->results($request);
        $data = json_decode($response->getContent(), true);

        $this->assertSame(0, $data['accepted']);
        $this->assertCount(1, $data['skipped']);
    }

    #[Test]
    public function skipsItemWithMissingSiteCheckId(): void
    {
        $agent = $this->buildAgent();

        $this->agentRepository->method('findByToken')->willReturn($agent);
        $this->siteCheckRepository->expects($this->never())->method('find');

        $payload = json_encode(['results' => [
            ['status' => 'ok'],
        ]]);
        $request = $this->buildRequest($agent->getId(), $payload);

        $response = $this->controller->results($request);
        $data = json_decode($response->getContent(), true);

        $this->assertSame(0, $data['accepted']);
        $this->assertCount(1, $data['skipped']);
    }

    #[Test]
    public function acceptsCheckedAtTimestamp(): void
    {
        $agent = $this->buildAgent();
        $check = $this->buildCheck($agent);

        $this->agentRepository->method('findByToken')->willReturn($agent);
        $this->siteCheckRepository->method('find')->willReturn($check);
        $this->stubAlertState($check);

        $persisted = [];
        $this->em->method('persist')->willReturnCallback(function ($entity) use (&$persisted) {
            $persisted[] = $entity;
        });

        $payload = json_encode(['results' => [
            ['site_check_id' => 1, 'status' => 'fail', 'checked_at' => '2026-06-15T14:00:00Z'],
        ]]);
        $request = $this->buildRequest($agent->getId(), $payload);

        $this->controller->results($request);

        $results = array_filter($persisted, fn ($e) => $e instanceof CheckResult);
        $result = array_values($results)[0] ?? null;

        $this->assertNotNull($result);
        $this->assertSame('2026-06-15 14:00:00', $result->getCheckedAt()->format('Y-m-d H:i:s'));
    }

    #[Test]
    public function returns422WhenTooManyResults(): void
    {
        $agent = $this->buildAgent();
        $this->agentRepository->method('findByToken')->willReturn($agent);

        $results = array_fill(0, 501, ['site_check_id' => 1, 'status' => 'ok']);
        $payload = json_encode(['results' => $results]);
        $request = $this->buildRequest($agent->getId(), $payload);

        $response = $this->controller->results($request);

        $this->assertSame(422, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertStringContainsString('500', $data['error']);
    }

    #[Test]
    public function truncatesLongMessage(): void
    {
        $agent = $this->buildAgent();
        $check = $this->buildCheck($agent);

        $this->agentRepository->method('findByToken')->willReturn($agent);
        $this->siteCheckRepository->method('find')->willReturn($check);
        $this->stubAlertState($check);

        $persisted = [];
        $this->em->method('persist')->willReturnCallback(function ($entity) use (&$persisted) {
            $persisted[] = $entity;
        });

        $longMessage = str_repeat('x', 2000);
        $payload = json_encode(['results' => [
            ['site_check_id' => 1, 'status' => 'ok', 'message' => $longMessage],
        ]]);
        $request = $this->buildRequest($agent->getId(), $payload);

        $this->controller->results($request);

        $results = array_values(array_filter($persisted, fn ($e) => $e instanceof CheckResult));
        $this->assertNotNull($results[0] ?? null);
        $this->assertSame(1024, mb_strlen($results[0]->getMessage()));
    }

    #[Test]
    public function ignoresInvalidCheckedAtFormat(): void
    {
        $agent = $this->buildAgent();
        $check = $this->buildCheck($agent);

        $this->agentRepository->method('findByToken')->willReturn($agent);
        $this->siteCheckRepository->method('find')->willReturn($check);
        $this->stubAlertState($check);

        $persisted = [];
        $this->em->method('persist')->willReturnCallback(function ($entity) use (&$persisted) {
            $persisted[] = $entity;
        });

        $before = new \DateTimeImmutable();

        $payload = json_encode(['results' => [
            ['site_check_id' => 1, 'status' => 'ok', 'checked_at' => 'yesterday'],
        ]]);
        $request = $this->buildRequest($agent->getId(), $payload);

        $this->controller->results($request);

        $results = array_values(array_filter($persisted, fn ($e) => $e instanceof CheckResult));
        $result = $results[0] ?? null;
        $this->assertNotNull($result);
        // Should fall back to constructor default (now), not "yesterday"
        $this->assertGreaterThanOrEqual($before->getTimestamp(), $result->getCheckedAt()->getTimestamp());
    }

    #[Test]
    public function ignoresNegativeResponseTimeMs(): void
    {
        $agent = $this->buildAgent();
        $check = $this->buildCheck($agent);

        $this->agentRepository->method('findByToken')->willReturn($agent);
        $this->siteCheckRepository->method('find')->willReturn($check);
        $this->stubAlertState($check);

        $persisted = [];
        $this->em->method('persist')->willReturnCallback(function ($entity) use (&$persisted) {
            $persisted[] = $entity;
        });

        $payload = json_encode(['results' => [
            ['site_check_id' => 1, 'status' => 'ok', 'response_time_ms' => -100],
        ]]);
        $request = $this->buildRequest($agent->getId(), $payload);

        $this->controller->results($request);

        $results = array_values(array_filter($persisted, fn ($e) => $e instanceof CheckResult));
        $this->assertNull($results[0]->getResponseTimeMs());
    }

    #[Test]
    public function skippedEntryAlwaysHasSiteCheckIdKey(): void
    {
        $agent = $this->buildAgent();
        $this->agentRepository->method('findByToken')->willReturn($agent);

        $payload = json_encode(['results' => [
            ['status' => 'ok'],
        ]]);
        $request = $this->buildRequest($agent->getId(), $payload);

        $response = $this->controller->results($request);
        $data = json_decode($response->getContent(), true);

        $this->assertArrayHasKey('site_check_id', $data['skipped'][0]);
    }

    #[Test]
    public function acceptsEmptyResultsList(): void
    {
        $agent = $this->buildAgent();

        $this->agentRepository->method('findByToken')->willReturn($agent);

        $payload = json_encode(['results' => []]);
        $request = $this->buildRequest($agent->getId(), $payload);

        $response = $this->controller->results($request);
        $data = json_decode($response->getContent(), true);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame(0, $data['accepted']);
    }

    #[Test]
    public function mixedResultsPartiallyAccepted(): void
    {
        $agent = $this->buildAgent();
        $check = $this->buildCheck($agent);

        $this->agentRepository->method('findByToken')->willReturn($agent);
        $this->siteCheckRepository->method('find')->willReturnCallback(
            fn (int $id) => $id === 1 ? $check : null
        );
        $this->stubAlertState($check);

        $payload = json_encode(['results' => [
            ['site_check_id' => 1, 'status' => 'ok'],
            ['site_check_id' => 999, 'status' => 'ok'],
        ]]);
        $request = $this->buildRequest($agent->getId(), $payload);

        $response = $this->controller->results($request);
        $data = json_decode($response->getContent(), true);

        $this->assertSame(1, $data['accepted']);
        $this->assertCount(1, $data['skipped']);
    }

    // Helpers

    private function buildAgent(int $id = 1): Agent
    {
        $agent = new Agent();
        $r = new \ReflectionProperty(Agent::class, 'id');
        $r->setValue($agent, $id);
        return $agent;
    }

    private function buildCheck(Agent $agent, CheckRunner $runner = CheckRunner::Agent): SiteCheck
    {
        $check = new SiteCheck();
        $check->setRunner($runner);
        $check->setAgent($runner === CheckRunner::Agent ? $agent : null);
        return $check;
    }

    private function stubAlertState(SiteCheck $check): void
    {
        $state = new AlertState();
        $this->alertStateRepository->method('findOrCreateForCheck')->with($check)->willReturn($state);
    }

    private function buildRequest(int $agentId, string $body): Request
    {
        return Request::create(
            '/api/v1/agent/results',
            'POST',
            [],
            [],
            [],
            ['HTTP_AUTHORIZATION' => 'Bearer test-token-' . $agentId],
            $body,
        );
    }
}
