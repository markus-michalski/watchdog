<?php

declare(strict_types=1);

namespace App\Command;

use App\Agent\DashboardClient;
use App\Agent\LocalCheckRunnerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Contracts\HttpClient\HttpClientInterface;

#[AsCommand(
    name: 'watchdog:agent:run',
    description: 'Run the watchdog agent — fetches checks from dashboard and pushes results',
)]
final class AgentRunCommand extends Command
{
    /** How often to refresh the check list from the dashboard (seconds) */
    private const CONFIG_REFRESH_INTERVAL = 300;

    /** How often the main loop ticks to check for due checks (seconds) */
    private const TICK_INTERVAL = 30;

    public function __construct(
        private readonly HttpClientInterface $http,
        private readonly LocalCheckRunnerInterface $localCheckRunner,
        private readonly LoggerInterface $logger,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('dashboard-url', null, InputOption::VALUE_REQUIRED, 'Dashboard base URL', getenv('WATCHDOG_DASHBOARD_URL') ?: '')
            ->addOption('token', null, InputOption::VALUE_REQUIRED, 'Agent bearer token', getenv('WATCHDOG_AGENT_TOKEN') ?: '')
            ->addOption('tick', null, InputOption::VALUE_REQUIRED, 'Tick interval in seconds', self::TICK_INTERVAL)
            ->addOption('config-refresh', null, InputOption::VALUE_REQUIRED, 'Config refresh interval in seconds', self::CONFIG_REFRESH_INTERVAL)
            ->addOption('run-once', null, InputOption::VALUE_NONE, 'Fetch config, run all checks once, push results, then exit — useful for testing');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $dashboardUrl = rtrim((string) $input->getOption('dashboard-url'), '/');
        $token = (string) $input->getOption('token');
        $tickInterval = max(1, (int) $input->getOption('tick'));
        $configRefreshInterval = max(60, (int) $input->getOption('config-refresh'));

        if ('' === $dashboardUrl) {
            $io->error('Dashboard URL is required. Set --dashboard-url or WATCHDOG_DASHBOARD_URL env var.');
            return Command::FAILURE;
        }

        if ('' === $token) {
            $io->error('Agent token is required. Set --token or WATCHDOG_AGENT_TOKEN env var.');
            return Command::FAILURE;
        }

        $client = new DashboardClient($this->http, $dashboardUrl, $token);

        if ($input->getOption('run-once')) {
            return $this->runOnce($client, $io);
        }

        $io->title('Watchdog Agent');
        $io->writeln(sprintf('Dashboard: %s', $dashboardUrl));
        $io->writeln(sprintf('Tick: %ds | Config refresh: %ds', $tickInterval, $configRefreshInterval));

        $running = true;
        $this->installSignalHandlers($running);

        $checks = [];
        /** @var array<int, int> $lastRunAt unix timestamps indexed by check id */
        $lastRunAt = [];
        /** @var array<int, string> $lastRunDate 'Y-m-d' indexed by check id, for run_at_time checks */
        $lastRunDate = [];
        $lastConfigRefresh = 0;
        $consecutiveConfigFailures = 0;
        /** How many consecutive config failures before we stop running stale checks */
        $maxConsecutiveConfigFailures = 5;

        while ($running) {
            $now = time();

            // Poll run-now flags on every tick — much faster than waiting for config refresh
            try {
                $runNowIds = $client->fetchRunNow();
                if ([] !== $runNowIds) {
                    $needsRefresh = $this->applyRunNowIds($runNowIds, $checks);
                    if ($needsRefresh) {
                        $this->logger->info('Unknown run-now IDs detected — forcing immediate config refresh', ['ids' => $runNowIds]);
                        $lastConfigRefresh = 0;
                    }
                }
            } catch (\Throwable $e) {
                $this->logger->warning('Run-now fetch failed', ['error' => $e->getMessage()]);
            }

            // Refresh config periodically
            if ($now - $lastConfigRefresh >= $configRefreshInterval) {
                try {
                    $config = $client->fetchConfig();
                    $checks = $config['checks'];
                    $lastConfigRefresh = $now;
                    $consecutiveConfigFailures = 0;
                    $this->logger->info('Config refreshed', ['check_count' => count($checks)]);
                    $io->writeln(sprintf('[%s] Config refreshed — %d checks', date('H:i:s'), count($checks)));
                } catch (\Throwable $e) {
                    ++$consecutiveConfigFailures;
                    $this->logger->error('Config fetch failed', [
                        'error' => $e->getMessage(),
                        'consecutive_failures' => $consecutiveConfigFailures,
                    ]);
                    $io->error(sprintf(
                        'Config fetch failed (%d/%d): %s — retrying in %ds',
                        $consecutiveConfigFailures,
                        $maxConsecutiveConfigFailures,
                        $e->getMessage(),
                        $tickInterval,
                    ));

                    // Stop running stale checks once the token appears revoked or the dashboard is unreachable for too long
                    if ($consecutiveConfigFailures >= $maxConsecutiveConfigFailures) {
                        $this->logger->error('Too many consecutive config failures — clearing check list to avoid running with stale config');
                        $io->error('Clearing check list after too many failures. Will resume once dashboard is reachable again.');
                        $checks = [];
                        $consecutiveConfigFailures = 0;
                    }
                }
            }

            // Find and run due checks
            $results = $this->processTick($checks, $now, $lastRunAt, $lastRunDate, $io);

            // Push all results in one batch
            if ([] !== $results) {
                try {
                    $client->pushResults($results);
                    $this->logger->info('Results pushed', ['count' => count($results)]);
                } catch (\Throwable $e) {
                    $this->logger->error('Results push failed', ['error' => $e->getMessage()]);
                    $io->warning(sprintf('Results push failed: %s', $e->getMessage()));
                }
            }

            sleep($tickInterval);
        }

        $io->writeln('Agent stopped gracefully.');
        $this->logger->info('Agent stopped');

        return Command::SUCCESS;
    }

    private function runOnce(DashboardClient $client, SymfonyStyle $io): int
    {
        $io->title('Watchdog Agent — run-once');

        try {
            $config = $client->fetchConfig();
        } catch (\Throwable $e) {
            $io->error(sprintf('Config fetch failed: %s', $e->getMessage()));
            return Command::FAILURE;
        }

        $checks = $config['checks'];
        $io->writeln(sprintf('Fetched %d check(s)', count($checks)));

        $results = [];
        foreach ($checks as $checkData) {
            $checkId = (int) $checkData['id'];
            try {
                $result = $this->localCheckRunner->run($checkId, $checkData['type'], $checkData['config']);
                $results[] = $result;
                $io->writeln(sprintf('  check=%d type=%s status=%s', $checkId, $checkData['type'], $result['status']));
            } catch (\Throwable $e) {
                $io->warning(sprintf('  check=%d type=%s ERROR: %s', $checkId, $checkData['type'], $e->getMessage()));
            }
        }

        if ([] !== $results) {
            try {
                $client->pushResults($results);
                $io->success(sprintf('Pushed %d result(s).', count($results)));
            } catch (\Throwable $e) {
                $io->error(sprintf('Push failed: %s', $e->getMessage()));
                return Command::FAILURE;
            }
        } else {
            $io->writeln('No results to push.');
        }

        return Command::SUCCESS;
    }

    private function installSignalHandlers(bool &$running): void
    {
        if (!extension_loaded('pcntl')) {
            return;
        }

        pcntl_async_signals(true);

        $handler = static function () use (&$running): void {
            $running = false;
        };

        pcntl_signal(\SIGTERM, $handler);
        pcntl_signal(\SIGINT, $handler);
    }

    /**
     * Merges run-now IDs from the dashboard into the in-memory checks array.
     * Sets run_now = true for known IDs and returns true if any IDs were unknown
     * (which should trigger an immediate config refresh so the agent picks up new checks).
     *
     * @param list<int>                   $runNowIds
     * @param array<array<string, mixed>> &$checks
     */
    public function applyRunNowIds(array $runNowIds, array &$checks): bool
    {
        $hasUnknown = false;

        foreach ($runNowIds as $id) {
            $found = false;
            foreach ($checks as &$check) {
                $checkId = $check['id'];
                if (is_int($checkId) && $checkId === $id) {
                    $check['run_now'] = true;
                    $found = true;
                    break;
                }
            }
            unset($check);
            if (!$found) {
                $hasUnknown = true;
            }
        }

        return $hasUnknown;
    }

    /**
     * Run one scheduler tick: find due checks, execute them, clear run_now flags in place.
     *
     * @param array<array<string, mixed>> &$checks      Live check list — run_now is mutated to false after execution
     * @param int                          $now          Current unix timestamp
     * @param array<int, int>             &$lastRunAt   Last run unix timestamp per check id
     * @param array<int, string>          &$lastRunDate Last run date ('Y-m-d') per check id (run_at_time checks only)
     * @return array<array<string, mixed>> Results ready to push
     */
    public function processTick(
        array &$checks,
        int $now,
        array &$lastRunAt,
        array &$lastRunDate,
        SymfonyStyle $io,
    ): array {
        $results = [];
        foreach ($checks as &$checkData) {
            $checkId = (int) $checkData['id'];
            $runNow = (bool) ($checkData['run_now'] ?? false);
            $runAtTime = is_string($checkData['run_at_time'] ?? null) && $checkData['run_at_time'] !== ''
                ? $checkData['run_at_time']
                : null;

            if (!$runNow) {
                if ($runAtTime !== null) {
                    // Daily check: run once at the specified time, not again until next day
                    $today = date('Y-m-d');
                    if (($lastRunDate[$checkId] ?? null) === $today) {
                        continue;
                    }
                    if (date('H:i') < $runAtTime) {
                        continue;
                    }
                } else {
                    $intervalSeconds = (int) $checkData['check_interval_minutes'] * 60;
                    $last = $lastRunAt[$checkId] ?? null;
                    $elapsed = $last !== null ? $now - $last : PHP_INT_MAX;

                    if ($elapsed < $intervalSeconds - 5) {
                        continue;
                    }
                }
            }

            try {
                $result = $this->localCheckRunner->run(
                    $checkId,
                    $checkData['type'],
                    $checkData['config'],
                );
                $results[] = $result;
                $lastRunAt[$checkId] = $now;
                if ($runAtTime !== null) {
                    $lastRunDate[$checkId] = date('Y-m-d');
                }

                $this->logger->debug('Check executed', [
                    'check_id' => $checkId,
                    'type' => $checkData['type'],
                    'status' => $result['status'],
                ]);
                $io->writeln(sprintf(
                    '[%s] check=%s type=%s status=%s',
                    date('H:i:s'),
                    $checkId,
                    $checkData['type'],
                    $result['status'],
                ));
            } catch (\Throwable $e) {
                $this->logger->error('Check execution failed', [
                    'check_id' => $checkId,
                    'type' => $checkData['type'],
                    'error' => $e->getMessage(),
                ]);
            } finally {
                // Clear run_now so the check doesn't fire on every tick until the next config refresh
                if ($runNow) {
                    $checkData['run_now'] = false;
                }
            }
        }
        unset($checkData);

        return $results;
    }
}
