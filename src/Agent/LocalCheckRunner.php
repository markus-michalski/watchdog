<?php

declare(strict_types=1);

namespace App\Agent;

use App\Check\CheckRegistry;
use App\Entity\SiteCheck;

final class LocalCheckRunner implements LocalCheckRunnerInterface
{
    public function __construct(
        private readonly CheckRegistry $registry,
    ) {
    }

    /**
     * Runs a single check and returns a result payload ready for the dashboard API.
     *
     * @param array<string, mixed> $config
     * @return array{site_check_id: int, status: string, message: string|null, response_time_ms: int|null, checked_at: string}
     */
    public function run(int $checkId, string $type, array $config): array
    {
        if (!$this->registry->has($type)) {
            return [
                'site_check_id' => $checkId,
                'status' => 'unknown',
                'message' => sprintf('Unknown check type: %s', $type),
                'response_time_ms' => null,
                'checked_at' => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
            ];
        }

        $check = $this->registry->get($type);

        // Minimal proxy — checks only call getConfig() and getType() on SiteCheck
        $proxy = new SiteCheck();
        $proxy->setType($type);
        $proxy->setConfig($config);

        $before = microtime(true);
        $result = $check->run($proxy);
        $elapsed = (int) round((microtime(true) - $before) * 1000);

        return [
            'site_check_id' => $checkId,
            'status' => $result->getStatus()->value,
            'message' => $result->getMessage(),
            'response_time_ms' => $result->getResponseTimeMs() ?? $elapsed,
            'checked_at' => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
        ];
    }
}
