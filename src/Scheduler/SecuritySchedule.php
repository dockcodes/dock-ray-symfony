<?php

declare(strict_types=1);

namespace Dock\Ray\Symfony\Scheduler;

use Symfony\Component\Scheduler\RecurringMessage;
use Symfony\Component\Scheduler\Schedule;
use Symfony\Component\Scheduler\ScheduleProviderInterface;

/**
 * A cheap tick every five minutes; the reporter decides whether a report is
 * due: every 6 hours (or the interval DockRay answers with, clamped to
 * 1-24 hours), a minute after a deploy, an hour after a truncated scan.
 */
final class SecuritySchedule implements ScheduleProviderInterface
{
    private ?Schedule $schedule = null;

    public function __construct(private readonly bool $enabled) {}

    public function getSchedule(): Schedule
    {
        if ($this->schedule !== null) {
            return $this->schedule;
        }
        $this->schedule = new Schedule;
        if ($this->enabled) {
            $this->schedule->add(RecurringMessage::every('5 minutes', new ReportSecurity));
        }

        return $this->schedule;
    }
}
