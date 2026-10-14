<?php

declare(strict_types=1);

namespace EventSolutions\NeventoSocialite\System;

use Cron\CronExpression;
use Illuminate\Console\Scheduling\CallbackEvent;
use Illuminate\Console\Scheduling\Event as ScheduledEvent;
use Illuminate\Console\Scheduling\Schedule;
use Throwable;

/** How often a scheduled task is expected to run, read from its cron expression, so the system can tell when one stops. */
class ScheduleCadence
{
    public static function minutes(ScheduledEvent $task): ?int
    {
        try {
            $cron = new CronExpression($task->expression);
            $first = $cron->getNextRunDate(now());
            $second = $cron->getNextRunDate($first);

            return max(1, (int) round(($second->getTimestamp() - $first->getTimestamp()) / 60));
        } catch (Throwable) {
            return null;
        }
    }

    /** The cadence of the schedule entry `nevento:tenants-run <command>` that runs this command per tenant, if there is one. */
    public static function forTenantCommand(string $command): ?int
    {
        foreach (app(Schedule::class)->events() as $event) {
            if (! $event instanceof CallbackEvent && preg_match('/tenants-run\s+[\'"]?'.preg_quote($command, '/').'[\'"]?(\s|$)/', (string) $event->command)) {
                return self::minutes($event);
            }
        }

        return null;
    }
}
