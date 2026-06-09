<?php

declare(strict_types=1);

namespace App\Scheduler;

use App\Message\RunSiteChecksMessage;
use App\Repository\SiteCheckRepository;
use Symfony\Component\Scheduler\Attribute\AsSchedule;
use Symfony\Component\Scheduler\RecurringMessage;
use Symfony\Component\Scheduler\Schedule;
use Symfony\Component\Scheduler\ScheduleProviderInterface;
use Symfony\Contracts\Cache\CacheInterface;

/**
 * Emits RunSiteChecksMessage every minute; CheckRunner decides per-check if it is due.
 * A 1-minute global tick avoids complex dynamic schedule rebuilding.
 */
#[AsSchedule('checks')]
final class CheckScheduleProvider implements ScheduleProviderInterface
{
    public function __construct(
        private readonly SiteCheckRepository $siteCheckRepository,
        private readonly CacheInterface $cache,
    ) {}

    public function getSchedule(): Schedule
    {
        return $this->cache->get('watchdog_schedule', function (): Schedule {
            $schedule = new Schedule();

            $checks = $this->siteCheckRepository->findAllActiveWithSites();
            foreach ($checks as $check) {
                $schedule->add(
                    RecurringMessage::every(
                        sprintf('%d minutes', $check->getCheckIntervalMinutes()),
                        new RunSiteChecksMessage((int) $check->getId()),
                    )
                );
            }

            return $schedule;
        });
    }
}
