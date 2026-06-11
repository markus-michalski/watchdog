<?php

declare(strict_types=1);

namespace App\Scheduler;

use App\Message\DispatchDueChecksMessage;
use Symfony\Component\Scheduler\Attribute\AsSchedule;
use Symfony\Component\Scheduler\RecurringMessage;
use Symfony\Component\Scheduler\Schedule;
use Symfony\Component\Scheduler\ScheduleProviderInterface;

/**
 * Emits one DispatchDueChecksMessage per minute.
 * The handler queries the DB at runtime and dispatches RunSiteChecksMessage
 * for each check that is due — no per-check cache, no scheduler restart needed
 * when checks are added or removed.
 */
#[AsSchedule('checks')]
final class CheckScheduleProvider implements ScheduleProviderInterface
{
    public function getSchedule(): Schedule
    {
        $schedule = new Schedule();
        $schedule->add(RecurringMessage::every('1 minute', new DispatchDueChecksMessage()));

        return $schedule;
    }
}
