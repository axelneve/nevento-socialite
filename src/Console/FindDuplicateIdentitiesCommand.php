<?php

declare(strict_types=1);

namespace EventSolutions\NeventoSocialite\Console;

use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Finds local accounts that were forked by the old email-keyed identity matching.
 *
 * Before 1.4.0 a user was matched on email, so changing an email address at the IDP
 * created a *second* local account carrying the same `idp_id`, leaving whatever was
 * attached to the first one orphaned. The signature is several rows sharing one
 * non-null `idp_id`.
 *
 * Reports by default. `--fix` only clears `idp_id` on the non-canonical rows so they
 * stop competing for the identity; it deliberately does not delete anything or
 * reassign related records, because what those mean is specific to each app and
 * guessing would quietly corrupt data.
 */
class FindDuplicateIdentitiesCommand extends Command
{
    protected $signature = 'nevento:duplicate-identities {--fix : Clear the identity column on the non-canonical rows}';

    protected $description = 'Find local accounts forked by the pre-1.4.0 email-keyed identity matching';

    public function handle(): int
    {
        /** @var class-string<Model> $modelClass */
        $modelClass = config('auth.providers.users.model', \App\Models\User::class);
        $instance = new $modelClass;
        $table = $instance->getTable();
        $key = $instance->getKeyName();

        // Apps disagree on the column: this package's own IdentitySyncService writes
        // idp_id, while the hand-rolled ones (rento, myOffice, kasso) use idp_user_id.
        $column = collect(['idp_id', 'idp_user_id'])
            ->first(fn (string $candidate): bool => Schema::hasColumn($table, $candidate));

        if ($column === null) {
            $this->components->info(
                "Table [{$table}] has neither an idp_id nor an idp_user_id column, so no local "
                .'account is keyed to an IDP identity here. Nothing to check.'
            );

            return self::SUCCESS;
        }

        $duplicated = DB::table($table)
            ->select($column)
            ->whereNotNull($column)
            ->where($column, '!=', '')
            ->groupBy($column)
            ->havingRaw('COUNT(*) > 1')
            ->pluck($column);

        if ($duplicated->isEmpty()) {
            $this->components->info('No duplicate identities found.');

            return self::SUCCESS;
        }

        $cleared = 0;

        foreach ($duplicated as $idpId) {
            $rows = DB::table($table)
                ->where($column, $idpId)
                // Same order IdentitySyncService resolves with, so "canonical" here
                // means the row a sign-in would actually land on.
                ->orderByDesc('updated_at')
                ->orderByDesc($key)
                ->get();

            $canonical = $rows->first();
            $stale = $rows->skip(1);

            $this->components->twoColumnDetail(
                "<fg=yellow>{$column} {$idpId}</>",
                $stale->count().' stale row(s)'
            );

            foreach ($rows as $row) {
                $marker = $row->{$key} === $canonical->{$key} ? '<fg=green>keep</>' : '<fg=red>stale</>';
                $this->line(sprintf(
                    '    %s  %s=%s  %s  updated %s',
                    $marker,
                    $key,
                    $row->{$key},
                    $row->email ?? '(no email)',
                    $row->updated_at ?? '(never)'
                ));
            }

            if ($this->option('fix')) {
                $cleared += DB::table($table)
                    ->whereIn($key, $stale->pluck($key)->all())
                    ->update([$column => null]);
            }
        }

        $this->newLine();

        if (! $this->option('fix')) {
            $this->components->warn(
                'Nothing changed. Re-run with --fix to clear the identity column on the stale rows. '
                .'Records attached to them are left alone — reassigning those is specific to this app.'
            );

            return self::SUCCESS;
        }

        $this->components->info("Cleared {$column} on {$cleared} stale row(s).");
        $this->components->warn('Records still attached to those rows were not moved; reconcile them before deleting anything.');

        return self::SUCCESS;
    }
}
