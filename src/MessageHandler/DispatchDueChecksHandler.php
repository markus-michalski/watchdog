<?php

declare(strict_types=1);

namespace App\MessageHandler;

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
        $checks = $this->siteCheckRepository->findAllActiveWithSites();
        if ([] === $checks) {
            return;
        }

        $now = new \DateTimeImmutable();
        $lastTimestamps = $this->checkResultRepository->findLatestTimestampsByChecks($checks);

        $dispatched = 0;
        foreach ($checks as $check) {
            $checkId = (int) $check->getId();

            if (!isset($lastTimestamps[$checkId])) {
                $this->bus->dispatch(new RunSiteChecksMessage($checkId));
                ++$dispatched;
                continue;
            }

            $elapsed = $now->getTimestamp() - $lastTimestamps[$checkId]->getTimestamp();
            // 30s tolerance absorbs tick jitter so checks don't drift by one full cycle
            if ($elapsed >= $check->getCheckIntervalMinutes() * 60 - 30) {
                $this->bus->dispatch(new RunSiteChecksMessage($checkId));
                ++$dispatched;
            }
        }

        $this->logger->info('Dispatch due checks tick', [
            'evaluated' => count($checks),
            'dispatched' => $dispatched,
        ]);
    }
}
