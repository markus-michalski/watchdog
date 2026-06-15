<?php

declare(strict_types=1);

namespace App\Tests\Unit\Controller\Api;

use App\Controller\Api\AgentConfigController;
use App\Entity\Agent;
use App\Entity\SiteCheck;
use App\Enum\CheckRunner;
use App\Repository\AgentRepository;
use App\Repository\SiteCheckRepository;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

#[AllowMockObjectsWithoutExpectations]
class AgentConfigControllerTest extends TestCase
{
    private AgentRepository&MockObject $agentRepository;
    private SiteCheckRepository&MockObject $siteCheckRepository;
    private AgentConfigController $controller;

    protected function setUp(): void
    {
        $this->agentRepository = $this->createMock(AgentRepository::class);
        $this->siteCheckRepository = $this->createMock(SiteCheckRepository::class);

        $this->controller = new AgentConfigController(
            $this->agentRepository,
            $this->siteCheckRepository,
        );
    }

    #[Test]
    public function returns401WithoutAuthHeader(): void
    {
        $request = Request::create('/api/v1/agent/config', 'GET');

        $response = $this->controller->config($request);

        $this->assertSame(401, $response->getStatusCode());
    }

    #[Test]
    public function returns401WithInvalidToken(): void
    {
        $request = Request::create('/api/v1/agent/config', 'GET', [], [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer bad-token',
        ]);
        $this->agentRepository->method('findByToken')->with('bad-token')->willReturn(null);

        $response = $this->controller->config($request);

        $this->assertSame(401, $response->getStatusCode());
    }

    #[Test]
    public function returnsAgentInfoAndEmptyChecksWhenNoneAssigned(): void
    {
        $agent = $this->buildAgent(1, 'prod-server');
        $this->agentRepository->method('findByToken')->willReturn($agent);
        $this->siteCheckRepository->method('findActiveByAgent')->with($agent)->willReturn([]);

        $response = $this->controller->config($this->buildRequest(1));
        $data = json_decode($response->getContent(), true);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame(1, $data['agent']['id']);
        $this->assertSame('prod-server', $data['agent']['name']);
        $this->assertSame([], $data['checks']);
    }

    #[Test]
    public function returnsAssignedChecksWithConfig(): void
    {
        $agent = $this->buildAgent(1, 'prod-server');
        $check = $this->buildCheck(42, 'disk', ['path' => '/', 'threshold_percent' => 85], 5);

        $this->agentRepository->method('findByToken')->willReturn($agent);
        $this->siteCheckRepository->method('findActiveByAgent')->with($agent)->willReturn([$check]);

        $response = $this->controller->config($this->buildRequest(1));
        $data = json_decode($response->getContent(), true);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertCount(1, $data['checks']);
        $this->assertSame(42, $data['checks'][0]['id']);
        $this->assertSame('disk', $data['checks'][0]['type']);
        $this->assertSame(['path' => '/', 'threshold_percent' => 85], $data['checks'][0]['config']);
        $this->assertSame(5, $data['checks'][0]['check_interval_minutes']);
        $this->assertNull($data['checks'][0]['run_at_time']);
    }

    #[Test]
    public function includesRunAtTimeWhenSet(): void
    {
        $agent = $this->buildAgent(1, 'prod-server');
        $check = $this->buildCheck(10, 'process', ['process_name' => 'nginx'], 60, '02:00');

        $this->agentRepository->method('findByToken')->willReturn($agent);
        $this->siteCheckRepository->method('findActiveByAgent')->willReturn([$check]);

        $response = $this->controller->config($this->buildRequest(1));
        $data = json_decode($response->getContent(), true);

        $this->assertSame('02:00', $data['checks'][0]['run_at_time']);
    }

    #[Test]
    public function returnsMultipleChecks(): void
    {
        $agent = $this->buildAgent(1, 'prod-server');
        $checks = [
            $this->buildCheck(1, 'disk', ['path' => '/'], 5),
            $this->buildCheck(2, 'redis', ['host' => 'localhost', 'port' => 6379], 1),
            $this->buildCheck(3, 'process', ['process_name' => 'php-fpm'], 5),
        ];

        $this->agentRepository->method('findByToken')->willReturn($agent);
        $this->siteCheckRepository->method('findActiveByAgent')->willReturn($checks);

        $response = $this->controller->config($this->buildRequest(1));
        $data = json_decode($response->getContent(), true);

        $this->assertCount(3, $data['checks']);
        $types = array_column($data['checks'], 'type');
        $this->assertContains('disk', $types);
        $this->assertContains('redis', $types);
        $this->assertContains('process', $types);
    }

    // Helpers

    private function buildAgent(int $id, string $name): Agent
    {
        $agent = new Agent();
        (new \ReflectionProperty(Agent::class, 'id'))->setValue($agent, $id);
        (new \ReflectionProperty(Agent::class, 'name'))->setValue($agent, $name);
        return $agent;
    }

    private function buildCheck(
        int $id,
        string $type,
        array $config,
        int $intervalMinutes,
        ?string $runAtTime = null,
    ): SiteCheck {
        $check = new SiteCheck();
        (new \ReflectionProperty(SiteCheck::class, 'id'))->setValue($check, $id);
        $check->setType($type);
        $check->setConfig($config);
        $check->setCheckIntervalMinutes($intervalMinutes);
        if ($runAtTime !== null) {
            $check->setRunAtTime($runAtTime);
        }
        $check->setRunner(CheckRunner::Agent);
        return $check;
    }

    private function buildRequest(int $agentId): Request
    {
        return Request::create(
            '/api/v1/agent/config',
            'GET',
            [],
            [],
            [],
            ['HTTP_AUTHORIZATION' => 'Bearer test-token-' . $agentId],
        );
    }
}
