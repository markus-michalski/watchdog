<?php

declare(strict_types=1);

namespace App\Tests\Unit\Agent;

use App\Agent\DashboardClient;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

#[AllowMockObjectsWithoutExpectations]
class DashboardClientTest extends TestCase
{
    private HttpClientInterface&MockObject $http;
    private DashboardClient $client;

    protected function setUp(): void
    {
        $this->http = $this->createMock(HttpClientInterface::class);
        $this->client = new DashboardClient($this->http, 'https://dashboard.example.com', 'secret-token');
    }

    #[Test]
    public function fetchConfigReturnsChecksArray(): void
    {
        $payload = [
            'agent' => ['id' => 1, 'name' => 'prod'],
            'checks' => [
                ['id' => 1, 'type' => 'disk', 'config' => ['path' => '/'], 'check_interval_minutes' => 5, 'run_at_time' => null],
            ],
        ];

        $response = $this->buildResponse(200, $payload);
        $this->http->expects($this->once())
            ->method('request')
            ->with('GET', 'https://dashboard.example.com/api/v1/agent/config', $this->arrayHasKey('headers'))
            ->willReturn($response);

        $result = $this->client->fetchConfig();

        $this->assertSame(1, $result['agent']['id']);
        $this->assertCount(1, $result['checks']);
    }

    #[Test]
    public function fetchConfigThrowsOn401(): void
    {
        $response = $this->buildResponse(401, ['error' => 'Unauthorized']);
        $this->http->method('request')->willReturn($response);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/401/');

        $this->client->fetchConfig();
    }

    #[Test]
    public function fetchConfigThrowsOnNon200(): void
    {
        $response = $this->buildResponse(503, []);
        $this->http->method('request')->willReturn($response);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/503/');

        $this->client->fetchConfig();
    }

    #[Test]
    public function pushResultsPostsToCorrectEndpoint(): void
    {
        $response = $this->buildResponse(200, ['accepted' => 1, 'skipped' => []]);

        $this->http->expects($this->once())
            ->method('request')
            ->with('POST', 'https://dashboard.example.com/api/v1/agent/results', $this->arrayHasKey('body'))
            ->willReturn($response);

        $this->client->pushResults([
            ['site_check_id' => 1, 'status' => 'ok', 'message' => null, 'response_time_ms' => 5, 'checked_at' => '2026-06-15T14:00:00+00:00'],
        ]);
    }

    #[Test]
    public function pushResultsDoesNothingForEmptyArray(): void
    {
        $this->http->expects($this->never())->method('request');

        $this->client->pushResults([]);
    }

    #[Test]
    public function pushResultsThrowsOn401(): void
    {
        $response = $this->buildResponse(401, ['error' => 'Unauthorized']);
        $this->http->method('request')->willReturn($response);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/401/');

        $this->client->pushResults([
            ['site_check_id' => 1, 'status' => 'ok', 'message' => null, 'response_time_ms' => null, 'checked_at' => '2026-06-15T14:00:00+00:00'],
        ]);
    }

    #[Test]
    public function pushResultsSendsBearerToken(): void
    {
        $response = $this->buildResponse(200, ['accepted' => 1, 'skipped' => []]);

        $this->http->expects($this->once())
            ->method('request')
            ->with('POST', $this->anything(), $this->callback(function ($opts) {
                return isset($opts['headers']['Authorization']) &&
                    str_starts_with($opts['headers']['Authorization'], 'Bearer ');
            }))
            ->willReturn($response);

        $this->client->pushResults([
            ['site_check_id' => 1, 'status' => 'ok', 'message' => null, 'response_time_ms' => null, 'checked_at' => '2026-06-15T14:00:00+00:00'],
        ]);
    }

    // --- fetchRunNow tests ---

    #[Test]
    public function fetchRunNowCallsCorrectEndpoint(): void
    {
        $response = $this->buildResponse(200, ['check_ids' => []]);

        $this->http->expects($this->once())
            ->method('request')
            ->with('GET', 'https://dashboard.example.com/api/v1/agent/run-now', $this->arrayHasKey('headers'))
            ->willReturn($response);

        $this->client->fetchRunNow();
    }

    #[Test]
    public function fetchRunNowReturnsEmptyArrayWhenNoneSet(): void
    {
        $response = $this->buildResponse(200, ['check_ids' => []]);
        $this->http->method('request')->willReturn($response);

        $this->assertSame([], $this->client->fetchRunNow());
    }

    #[Test]
    public function fetchRunNowReturnsCheckIds(): void
    {
        $response = $this->buildResponse(200, ['check_ids' => [42, 7]]);
        $this->http->method('request')->willReturn($response);

        $this->assertSame([42, 7], $this->client->fetchRunNow());
    }

    #[Test]
    public function fetchRunNowThrowsOn401(): void
    {
        $response = $this->buildResponse(401, ['error' => 'Unauthorized']);
        $this->http->method('request')->willReturn($response);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/401/');

        $this->client->fetchRunNow();
    }

    #[Test]
    public function fetchRunNowThrowsOnNon200(): void
    {
        $response = $this->buildResponse(503, []);
        $this->http->method('request')->willReturn($response);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/503/');

        $this->client->fetchRunNow();
    }

    // Helpers

    private function buildResponse(int $statusCode, array $body): ResponseInterface&MockObject
    {
        $response = $this->createMock(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn($statusCode);
        $response->method('toArray')->willReturn($body);
        return $response;
    }
}
