<?php

declare(strict_types=1);

namespace Dock\Ray\Symfony\Scheduler;

use Symfony\Component\Scheduler\RecurringMessage;
use Symfony\Component\Scheduler\Schedule;
use Symfony\Component\Scheduler\ScheduleProviderInterface;

final class InventorySchedule implements ScheduleProviderInterface
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
            $this->schedule->add(RecurringMessage::every('1 hour', new ReportInventory));
        }

        return $this->schedule;
    }
}
