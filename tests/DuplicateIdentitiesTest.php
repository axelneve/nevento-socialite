<?php

declare(strict_types=1);

namespace EventSolutions\NeventoSocialite\Tests;

use EventSolutions\NeventoSocialite\IdentitySyncService;
use EventSolutions\NeventoSocialite\Tests\Fixtures\TestUser;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Laravel\Socialite\Two\User as SocialiteUser;
use PHPUnit\Framework\Attributes\Test;

/**
 * Covers the residue of the pre-1.4.0 email-keyed matching: several local rows
 * carrying one idp_id.
 */
class DuplicateIdentitiesTest extends TestCase
{
    #[Test]
    public function a_clean_install_reports_nothing(): void
    {
        TestUser::query()->create(['name' => 'Ada', 'email' => 'ada@klant.nl', 'idp_id' => '1']);

        $this->artisan('nevento:duplicate-identities')
            ->expectsOutputToContain('No duplicate identities found.')
            ->assertSuccessful();
    }

    #[Test]
    public function it_handles_apps_that_use_idp_user_id_instead(): void
    {
        // rento, myOffice and kasso key on idp_user_id, not idp_id. Erroring out there
        // makes the command useless on more than half the fleet.
        $this->dropIdpIdColumn();
        Schema::table('users', fn (Blueprint $table) => $table->string('idp_user_id')->nullable());

        DB::table('users')->insert([
            ['name' => 'A', 'email' => 'a@k.nl', 'idp_user_id' => '7', 'updated_at' => now()->subYear()],
            ['name' => 'A', 'email' => 'b@k.nl', 'idp_user_id' => '7', 'updated_at' => now()],
        ]);

        $this->artisan('nevento:duplicate-identities --fix')->assertSuccessful();

        $this->assertSame(1, DB::table('users')->where('idp_user_id', '7')->count());
    }

    #[Test]
    public function an_app_with_neither_column_is_reported_not_failed(): void
    {
        $this->dropIdpIdColumn();

        // Not every app links local users to an IDP identity; that is not an error.
        $this->artisan('nevento:duplicate-identities')
            ->expectsOutputToContain('Nothing to check.')
            ->assertSuccessful();
    }

    #[Test]
    public function it_reports_forked_accounts_without_changing_anything(): void
    {
        $this->seedFork();

        $this->artisan('nevento:duplicate-identities')->assertSuccessful();

        // Dry by default: deleting or re-pointing accounts is not something to do
        // because a command was run without arguments.
        $this->assertSame(2, TestUser::query()->where('idp_id', '42')->count());
    }

    #[Test]
    public function fix_clears_the_stale_row_and_keeps_the_one_in_use(): void
    {
        [$old, $current] = $this->seedFork();

        $this->artisan('nevento:duplicate-identities --fix')->assertSuccessful();

        $this->assertNull($old->fresh()->idp_id);
        $this->assertSame('42', (string) $current->fresh()->idp_id);

        // The row itself survives: whatever is attached to it is this app's business,
        // not the package's.
        $this->assertNotNull($old->fresh());
    }

    #[Test]
    public function a_sign_in_lands_on_the_account_the_person_has_been_using(): void
    {
        [$old, $current] = $this->seedFork();

        $user = $this->sync('42', 'nieuw@klant.nl', 'Ada');

        // Not the dormant original — moving someone to an account they abandoned
        // months ago would look like their data vanished.
        $this->assertSame($current->id, $user->id);
        $this->assertNotSame($old->id, $user->id);
    }

    #[Test]
    public function after_fixing_the_match_is_unambiguous(): void
    {
        [$old, $current] = $this->seedFork();

        $this->artisan('nevento:duplicate-identities --fix')->assertSuccessful();

        $user = $this->sync('42', 'nieuw@klant.nl', 'Ada');

        $this->assertSame($current->id, $user->id);
        $this->assertSame(1, TestUser::query()->where('idp_id', '42')->count());
    }

    /**
     * SQLite refuses to drop a column an index still points at.
     */
    private function dropIdpIdColumn(): void
    {
        Schema::table('users', fn (Blueprint $table) => $table->dropIndex(['idp_id']));
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('idp_id'));
    }

    /**
     * The exact shape the old code produced: one identity, two rows, the newer one
     * being the one actually signed into.
     *
     * @return array{0: TestUser, 1: TestUser}
     */
    private function seedFork(): array
    {
        $old = TestUser::query()->create([
            'name' => 'Ada', 'email' => 'oud@klant.nl', 'idp_id' => '42',
            'created_at' => now()->subYear(), 'updated_at' => now()->subYear(),
        ]);

        $current = TestUser::query()->create([
            'name' => 'Ada', 'email' => 'nieuw@klant.nl', 'idp_id' => '42',
            'created_at' => now()->subMonth(), 'updated_at' => now()->subDay(),
        ]);

        return [$old, $current];
    }

    private function sync(string $idpId, string $email, string $name): TestUser
    {
        $raw = [
            'id' => $idpId,
            'name' => $name,
            'email' => $email,
            'workspace' => ['id' => 1, 'name' => 'WS', 'roles' => ['admin']],
            'workspaces' => [],
        ];

        $oauthUser = (new SocialiteUser)->setRaw($raw)->map([
            'id' => $idpId, 'name' => $name, 'email' => $email,
        ]);

        /** @var TestUser $user */
        $user = app(IdentitySyncService::class)->sync($raw, $oauthUser);

        return $user;
    }
}
