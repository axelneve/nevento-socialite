<?php

declare(strict_types=1);

namespace EventSolutions\NeventoSocialite\System;

use Illuminate\Console\Command;
use Throwable;

/**
 * Drop-in for stancl's `tenants:run`, same arguments. Differences, which are the point:
 *  - every tenant's result is reported to nevento-system (heartbeat per tenant), so a failure for one workspace is seen;
 *  - a tenant that fails does not stop the others;
 *  - the exit code is non-zero when any tenant failed, so the scheduled task itself shows as failed (stancl's returns 0
 *    always, which is how failing per-tenant jobs went unnoticed for weeks).
 *
 * Use it in the schedule: `Schedule::command('nevento:tenants-run people:reconcile')->dailyAt('05:45');`
 */
class TenantsRunCommand extends Command
{
    protected $signature = "nevento:tenants-run {commandname : The command's name.}
                            {--tenants=* : The tenant(s) to run the command for. Default: all}
                            {--argument=* : The arguments to pass to the command, as name=value.}
                            {--option=* : The options to pass to the command, as name=value.}";

    protected $description = 'Run a command for every tenant, reporting each result to nevento-system.';

    public function handle(SystemClient $client): int
    {
        $name = (string) $this->argument('commandname');
        $arguments = $this->keyValues($this->option('argument'), '');
        $options = $this->keyValues($this->option('option'), '--');
        $cadence = ScheduleCadence::forTenantCommand($name);
        $failed = [];

        $this->forEachTenant(function (string $tenant) use ($client, $name, $arguments, $options, $cadence, &$failed): void {
            $this->line("Tenant: {$tenant}");
            $started = hrtime(true);
            $message = null;

            try {
                $exit = (int) $this->call($name, array_merge($arguments, $options));
                $message = $exit === 0 ? null : "exitcode {$exit}";
            } catch (Throwable $e) {
                $exit = 1;
                $message = mb_substr($e->getMessage(), 0, 400);
                $this->error("{$tenant}: {$message}");
            }

            if ($exit !== 0) {
                $failed[] = $tenant;
            }

            $client->heartbeat($name, $exit === 0 ? 'ok' : 'failed', $tenant, (int) round((hrtime(true) - $started) / 1_000_000), $message, $cadence);
        });

        if ($failed !== []) {
            $this->error("{$name} is mislukt voor: ".implode(', ', $failed));

            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    /**
     * Runs the callback inside each tenant (all, or the ones in --tenants). Overridable so it can be tested without
     * stancl/tenancy; the default is stancl's own loop.
     *
     * @param  callable(string): void  $callback
     */
    protected function forEachTenant(callable $callback): void
    {
        tenancy()->runForMultiple($this->option('tenants') ?: null, fn ($tenant) => $callback((string) $tenant->getTenantKey()));
    }

    /**
     * @param  array<int, string>|mixed  $pairs
     * @return array<string, string>
     */
    private function keyValues(mixed $pairs, string $prefix): array
    {
        $result = [];

        foreach ((array) $pairs as $pair) {
            [$key, $value] = array_pad(explode('=', (string) $pair, 2), 2, '');
            $result[$prefix.$key] = $value;
        }

        return $result;
    }
}
