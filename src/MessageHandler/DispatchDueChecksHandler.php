<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Entity\SiteCheck;
use App\Message\DispatchDueChecksMessage;
use App\Message\RunSiteChecksMessage;
use App\Repository\CheckResultRepository;
use App\Repository\SiteCheckRepository;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsMessageHandler]
final class DispatchDueChecksHandler
{
    public function __construct(
        private readonly SiteCheckRepository $siteCheckRepository,
        private readonly CheckResultRepository $checkResultRepository,
        private readonly MessageBusInterface $bus,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function __invoke(DispatchDueChecksMessage $_message): void
    {
        $checks = $this->siteCheckRepository->findDashboardChecks();
        if ([] === $checks) {
            return;
        }

        $now = new \DateTimeImmutable();
        $lastTimestamps = $this->checkResultRepository->findLatestTimestampsByChecks($checks);

        $dispatched = 0;
        foreach ($checks as $check) {
            $checkId = (int) $check->getId();
            $lastRun = $lastTimestamps[$checkId] ?? null;

            if ($this->isDue($check, $now, $lastRun)) {
                $this->bus->dispatch(new RunSiteChecksMessage($checkId));
                ++$dispatched;
            }
        }

        $this->logger->info('Dispatch due checks tick', [
            'evaluated' => count($checks),
            'dispatched' => $dispatched,
        ]);
    }

    private function isDue(SiteCheck $check, \DateTimeImmutable $now, ?\DateTimeImmutable $lastRun): bool
    {
        if (null !== $check->getRunAtTime()) {
            try {
                $scheduledToday = new \DateTimeImmutable('today '.$check->getRunAtTime());
            } catch (\Exception $e) {
                $this->logger->error('Invalid run_at_time value, skipping check', [
                    'check_id' => $check->getId(),
                    'run_at_time' => $check->getRunAtTime(),
                ]);

                return false;
            }

            if ($now < $scheduledToday) {
                return false;
            }

            return null === $lastRun || $lastRun < $scheduledToday;
        }

        if (null === $lastRun) {
            return true;
        }

        $elapsed = $now->getTimestamp() - $lastRun->getTimestamp();

        return $elapsed >= $check->getCheckIntervalMinutes() * 60 - 30;
    }
}
