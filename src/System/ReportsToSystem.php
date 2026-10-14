<?php

declare(strict_types=1);

namespace EventSolutions\NeventoSocialite\System;

use Cron\CronExpression;
use Illuminate\Console\Events\CommandFinished;
use Illuminate\Console\Events\ScheduledTaskFailed;
use Illuminate\Console\Events\ScheduledTaskFinished;
use Illuminate\Console\Scheduling\CallbackEvent;
use Illuminate\Console\Scheduling\Event as ScheduledEvent;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Events\Dispatcher;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Support\Str;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Throwable;

/**
 * Turns what happens in the app into reports for nevento-system.
 *
 *  - A scheduled task that ran: one app-wide heartbeat, with its expected cadence read from its cron expression.
 *  - A command that ran for one tenant (apps schedule per-tenant work as `tenants:run <command>`, and that wrapper
 *    reports success even when the command failed inside a tenant): one heartbeat for that tenant, from the command's
 *    own exit code. This is what makes a failure for one workspace visible.
 *  - A failed queue job, and a mail that could not be sent: a signal.
 *
 * Every listener swallows its own errors: reporting must never break the app.
 */
class ReportsToSystem
{
    public function __construct(private readonly SystemClient $client) {}

    public function subscribe(Dispatcher $events): void
    {
        $events->listen(ScheduledTaskFinished::class, $this->guarded($this->scheduledFinished(...)));
        $events->listen(ScheduledTaskFailed::class, $this->guarded($this->scheduledFailed(...)));
        $events->listen(CommandFinished::class, $this->guarded($this->commandFinished(...)));
        $events->listen(JobFailed::class, $this->guarded($this->jobFailed(...)));
        $events->listen(MessageLogged::class, $this->guarded($this->messageLogged(...)));
    }

    private function scheduledFinished(ScheduledTaskFinished $event): void
    {
        $name = $this->taskName($event->task);

        if ($this->ignored($name) || $event->task->runInBackground) {
            return;
        }

        $failed = ($event->task->exitCode ?? 0) !== 0;

        $this->client->heartbeat(
            $name, $failed ? 'failed' : 'ok', null, (int) round($event->runtime * 1000),
            $failed ? "exitcode {$event->task->exitCode}" : null, $this->expectedMinutes($event->task),
        );
    }

    /** A command that exits non-zero is reported by scheduledFinished; this is for closures that throw. */
    private function scheduledFailed(ScheduledTaskFailed $event): void
    {
        if (! $event->task instanceof CallbackEvent || $this->ignored($this->taskName($event->task))) {
            return;
        }

        $this->client->heartbeat($this->taskName($event->task), 'failed', null, null, Str::limit($event->exception->getMessage(), 400, ''), $this->expectedMinutes($event->task));
    }

    private function commandFinished(CommandFinished $event): void
    {
        $tenant = $this->tenantKey();
        $scheduled = $this->scheduledTenantCommands();

        if ($tenant === null || $event->command === null || ! isset($scheduled[$event->command]) || $this->ignored($event->command)) {
            return;
        }

        $this->client->heartbeat(
            $event->command, $event->exitCode === 0 ? 'ok' : 'failed', $tenant, null,
            $event->exitCode === 0 ? null : "exitcode {$event->exitCode}", $this->expectedMinutes($scheduled[$event->command]),
        );
    }

    private function jobFailed(JobFailed $event): void
    {
        $tenant = $this->tenantKey();
        $message = $event->job->resolveName().': '.Str::limit($event->exception->getMessage(), 300, '');

        $this->client->signal('failed_job', $tenant, $message);

        if ($event->exception instanceof TransportExceptionInterface) {
            $this->client->signal('mail_failure', $tenant, Str::limit($event->exception->getMessage(), 400, ''));
        }
    }

    /** A mail that bubbled up as an unhandled exception is logged with the transport exception in its context. */
    private function messageLogged(MessageLogged $event): void
    {
        if (($event->context['exception'] ?? null) instanceof TransportExceptionInterface) {
            $this->client->signal('mail_failure', $this->tenantKey(), Str::limit($event->context['exception']->getMessage(), 400, ''));
        }
    }

    private function taskName(ScheduledEvent $task): string
    {
        if ($task instanceof CallbackEvent) {
            return $task->description ?: 'closure';
        }

        $command = (string) $task->command;

        // The command reads `'php' 'artisan' name args` (or with double quotes on Windows): the name is what follows "artisan".
        return $task->description ?: (preg_match('/artisan["\']?\s+(.+)$/', $command, $m) ? trim($m[1], " '\"") : $command);
    }

    private function expectedMinutes(ScheduledEvent $task): ?int
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

    /**
     * The commands the app schedules per tenant (`tenants:run <command>`), with their schedule entry.
     *
     * @return array<string, ScheduledEvent>
     */
    private function scheduledTenantCommands(): array
    {
        $commands = [];

        foreach (app(Schedule::class)->events() as $event) {
            if (! $event instanceof CallbackEvent && preg_match("/tenants:run\s+([^\s'\"]+)/", (string) $event->command, $m)) {
                $commands[$m[1]] = $event;
            }
        }

        return $commands;
    }

    protected function tenantKey(): ?string
    {
        if (! function_exists('tenancy') || ! function_exists('tenant') || ! tenancy()->initialized) {
            return null;
        }

        $tenant = tenant();

        return $tenant === null ? null : (string) $tenant->getTenantKey();
    }

    private function ignored(string $name): bool
    {
        foreach ((array) config('nevento-system.ignore', []) as $pattern) {
            if (fnmatch((string) $pattern, $name)) {
                return true;
            }
        }

        return false;
    }

    /** @param callable(mixed): void $listener */
    private function guarded(callable $listener): \Closure
    {
        return function (mixed $event) use ($listener): void {
            try {
                $listener($event);
            } catch (Throwable) {
                // Reporting never breaks the app.
            }
        };
    }
}
