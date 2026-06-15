<?php

declare(strict_types=1);

namespace App\Command;

use App\Agent\DashboardClient;
use App\Agent\LocalCheckRunner;
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
        private readonly LocalCheckRunner $localCheckRunner,
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
            ->addOption('config-refresh', null, InputOption::VALUE_REQUIRED, 'Config refresh interval in seconds', self::CONFIG_REFRESH_INTERVAL);
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

        $io->title('Watchdog Agent');
        $io->writeln(sprintf('Dashboard: %s', $dashboardUrl));
        $io->writeln(sprintf('Tick: %ds | Config refresh: %ds', $tickInterval, $configRefreshInterval));

        $running = true;
        $this->installSignalHandlers($running);

        $checks = [];
        /** @var array<int, \DateTimeImmutable> $lastRunAt indexed by check id */
        $lastRunAt = [];
        $lastConfigRefresh = 0;

        while ($running) {
            $now = time();

            // Refresh config periodically
            if ($now - $lastConfigRefresh >= $configRefreshInterval) {
                try {
                    $config = $client->fetchConfig();
                    $checks = $config['checks'];
                    $lastConfigRefresh = $now;
                    $this->logger->info('Config refreshed', ['check_count' => count($checks)]);
                    $io->writeln(sprintf('[%s] Config refreshed — %d checks', date('H:i:s'), count($checks)));
                } catch (\Throwable $e) {
                    $this->logger->error('Config fetch failed', ['error' => $e->getMessage()]);
                    $io->error(sprintf('Config fetch failed: %s — retrying in %ds', $e->getMessage(), $tickInterval));
                }
            }

            // Find and run due checks
            $results = [];
            foreach ($checks as $checkData) {
                $checkId = (int) $checkData['id'];
                $intervalSeconds = (int) $checkData['check_interval_minutes'] * 60;
                $last = $lastRunAt[$checkId] ?? null;
                $elapsed = $last !== null ? $now - $last->getTimestamp() : PHP_INT_MAX;

                if ($elapsed < $intervalSeconds - 5) {
                    continue;
                }

                try {
                    $result = $this->localCheckRunner->run(
                        $checkId,
                        $checkData['type'],
                        $checkData['config'],
                    );
                    $results[] = $result;
                    $lastRunAt[$checkId] = new \DateTimeImmutable();

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
                }
            }

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

            if ($running) {
                sleep($tickInterval);
            }
        }

        $io->writeln('Agent stopped gracefully.');
        $this->logger->info('Agent stopped');

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
}
