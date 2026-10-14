<?php

declare(strict_types=1);

namespace EventSolutions\NeventoSocialite\System;

use Illuminate\Console\Command;
use Illuminate\Database\Migrations\Migrator;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Tells nevento-system, for every tenant, whether its database opens and how many tenant migrations are pending.
 * Scheduled hourly by the package when stancl/tenancy is installed and reporting is on.
 */
class ReportTenantsCommand extends Command
{
    protected $signature = 'nevento:system-report-tenants';

    protected $description = 'Meldt de gezondheid van elke tenant-database aan nevento-system.';

    public function handle(SystemClient $client): int
    {
        $model = config('tenancy.tenant_model');

        if (! $client->enabled() || ! is_string($model) || ! class_exists($model)) {
            return self::SUCCESS;
        }

        $rows = [];

        foreach ($model::all() as $tenant) {
            $rows[] = $this->inspect($tenant);
        }

        $client->tenants($rows);
        $this->info(count($rows).' tenant(s) gemeld.');

        return self::SUCCESS;
    }

    /** @return array{tenant: string, database_ok: bool, pending_migrations: int} */
    private function inspect(object $tenant): array
    {
        $row = ['tenant' => (string) $tenant->getTenantKey(), 'database_ok' => false, 'pending_migrations' => 0];

        try {
            $tenant->run(function () use (&$row): void {
                DB::connection()->getPdo();
                $row['database_ok'] = true;
                $row['pending_migrations'] = $this->pendingMigrations();
            });
        } catch (Throwable) {
            // database_ok stays false: that is the report.
        }

        return $row;
    }

    private function pendingMigrations(): int
    {
        /** @var Migrator $migrator */
        $migrator = app('migrator');
        $paths = (array) config('tenancy.migration_parameters.--path', [database_path('migrations/tenant')]);
        $files = array_keys($migrator->getMigrationFiles($paths));

        return count(array_diff($files, $migrator->getRepository()->getRan()));
    }
}
