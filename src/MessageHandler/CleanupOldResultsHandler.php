<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Message\CleanupOldResultsMessage;
use App\Repository\CheckResultRepository;
use App\Repository\SiteCheckRepository;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class CleanupOldResultsHandler
{
    public function __construct(
        private readonly SiteCheckRepository $siteCheckRepository,
        private readonly CheckResultRepository $checkResultRepository,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function __invoke(CleanupOldResultsMessage $_message): void
    {
        $checks = $this->siteCheckRepository->findWithRetentionPolicy();
        $totalDeleted = 0;

        foreach ($checks as $check) {
            $days = $check->getRetentionDays();
            if ($days === null) {
                continue;
            }

            $cutoff = new \DateTimeImmutable(sprintf('-%d days', $days));
            $deleted = $this->checkResultRepository->deleteOlderThanForCheck($check, $cutoff);

            if ($deleted > 0) {
                $this->logger->info('Cleaned up old results for check', [
                    'check_id' => $check->getId(),
                    'retention_days' => $days,
                    'deleted' => $deleted,
                ]);
            }

            $totalDeleted += $deleted;
        }

        $this->logger->info('Retention cleanup complete', [
            'checks_evaluated' => count($checks),
            'total_deleted' => $totalDeleted,
        ]);
    }
}
