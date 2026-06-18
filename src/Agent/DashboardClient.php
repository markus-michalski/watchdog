<?php

declare(strict_types=1);

namespace App\Agent;

use Symfony\Contracts\HttpClient\HttpClientInterface;

final class DashboardClient
{
    public function __construct(
        private readonly HttpClientInterface $http,
        private readonly string $dashboardUrl,
        private readonly string $token,
    ) {
    }

    /**
     * Fetches agent config from dashboard.
     * Returns the parsed response body or throws on failure.
     *
     * @return array{agent: array{id: int, name: string}, checks: list<array{id: int, type: string, config: array<string,mixed>, check_interval_minutes: int, run_at_time: string|null}>}
     * @throws \RuntimeException
     */
    public function fetchConfig(): array
    {
        $response = $this->http->request('GET', $this->dashboardUrl . '/api/v1/agent/config', [
            'headers' => ['Authorization' => 'Bearer ' . $this->token],
            'timeout' => 10,
        ]);

        if ($response->getStatusCode() === 401) {
            throw new \RuntimeException('Agent token rejected by dashboard (401). Check WATCHDOG_AGENT_TOKEN.');
        }

        if ($response->getStatusCode() !== 200) {
            throw new \RuntimeException(sprintf('Config fetch failed with HTTP %d', $response->getStatusCode()));
        }

        /** @var array{agent: array{id: int, name: string}, checks: list<array{id: int, type: string, config: array<string,mixed>, check_interval_minutes: int, run_at_time: string|null}>} $data */
        $data = $response->toArray();

        return $data;
    }

    /**
     * Fetches full check data for checks with run_now = true for this agent.
     * Called on every tick (every 30s). Server clears run_now flags on delivery.
     * Returns full check config so the agent can run checks as one-shots even
     * when they are not in the in-memory config (e.g. checks of inactive clients).
     *
     * @return list<array{id: int, type: string, config: array<string,mixed>, check_interval_minutes: int, run_at_time: string|null}>
     * @throws \RuntimeException
     */
    public function fetchRunNow(): array
    {
        $response = $this->http->request('GET', $this->dashboardUrl . '/api/v1/agent/run-now', [
            'headers' => ['Authorization' => 'Bearer ' . $this->token],
            'timeout' => 5,
        ]);

        if ($response->getStatusCode() === 401) {
            throw new \RuntimeException('Agent token rejected by dashboard (401). Check WATCHDOG_AGENT_TOKEN.');
        }

        if ($response->getStatusCode() !== 200) {
            throw new \RuntimeException(sprintf('Run-now fetch failed with HTTP %d', $response->getStatusCode()));
        }

        /** @var array{checks: list<array{id: int, type: string, config: array<string,mixed>, check_interval_minutes: int, run_at_time: string|null}>} $data */
        $data = $response->toArray();

        return $data['checks'];
    }

    /**
     * Pushes check results to dashboard.
     *
     * @param list<array{site_check_id: int, status: string, message: string|null, response_time_ms: int|null, checked_at: string}> $results
     * @throws \RuntimeException
     */
    public function pushResults(array $results): void
    {
        if ([] === $results) {
            return;
        }

        $response = $this->http->request('POST', $this->dashboardUrl . '/api/v1/agent/results', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->token,
                'Content-Type' => 'application/json',
            ],
            'body' => json_encode(['results' => $results], \JSON_THROW_ON_ERROR),
            'timeout' => 15,
        ]);

        if ($response->getStatusCode() === 401) {
            throw new \RuntimeException('Agent token rejected by dashboard (401).');
        }

        if ($response->getStatusCode() !== 200) {
            throw new \RuntimeException(sprintf('Results push failed with HTTP %d', $response->getStatusCode()));
        }
    }
}
