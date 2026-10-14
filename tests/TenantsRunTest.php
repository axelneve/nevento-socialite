<?php

declare(strict_types=1);

namespace EventSolutions\NeventoSocialite\Tests;

use EventSolutions\NeventoSocialite\System\SystemClient;
use EventSolutions\NeventoSocialite\System\TenantsRunCommand;
use Illuminate\Console\Command;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;

/** The tenants:run replacement, run without stancl/tenancy: the tenant loop is replaced by a plain list. */
class TenantsRunTest extends TestCase
{
    /** @var list<string> */
    public static array $ran = [];

    public static ?string $current = null;

    protected function setUp(): void
    {
        parent::setUp();

        SystemClient::resetBackoff();
        self::$ran = [];
        config(['nevento-system.url' => 'https://system.test', 'nevento-system.token' => 'sys_secret']);
        Http::fake(['system.test/*' => Http::response('', 204)]);

        // The command that is run per tenant: fails for ws-2, throws for ws-3.
        Artisan::registerCommand(new class extends Command
        {
            protected $signature = 'fake:work {--mode=}';

            public function handle(): int
            {
                TenantsRunTest::$ran[] = (string) TenantsRunTest::$current.':'.($this->option('mode') ?: '-');

                if (TenantsRunTest::$current === 'ws-3') {
                    throw new \RuntimeException('database weg');
                }

                return TenantsRunTest::$current === 'ws-2' ? 1 : 0;
            }
        });

        Artisan::registerCommand(new class extends TenantsRunCommand
        {
            protected function forEachTenant(callable $callback): void
            {
                foreach (['ws-1', 'ws-2', 'ws-3', 'ws-4'] as $tenant) {
                    TenantsRunTest::$current = $tenant;
                    $callback($tenant);
                }
            }
        });
    }

    public function test_every_tenant_is_run_reported_and_a_failure_does_not_stop_the_rest(): void
    {
        app(Schedule::class)->command('nevento:tenants-run fake:work')->dailyAt('05:45');

        $this->artisan('nevento:tenants-run', ['commandname' => 'fake:work', '--option' => ['mode=fast']])
            ->expectsOutputToContain('fake:work is mislukt voor: ws-2, ws-3')
            ->assertExitCode(1);

        $this->assertSame(['ws-1:fast', 'ws-2:fast', 'ws-3:fast', 'ws-4:fast'], self::$ran);

        $reported = [];
        Http::assertSent(function (Request $request) use (&$reported) {
            $reported[$request['tenant']] = $request['status'].'|'.($request['message'] ?? '').'|'.($request['expected_every_minutes'] ?? '');

            return true;
        });

        $this->assertSame([
            'ws-1' => 'ok||1440',
            'ws-2' => 'failed|exitcode 1|1440',
            'ws-3' => 'failed|database weg|1440',
            'ws-4' => 'ok||1440',
        ], $reported);
    }

    public function test_it_succeeds_quietly_without_a_token(): void
    {
        config(['nevento-system.token' => null]);

        $this->artisan('nevento:tenants-run', ['commandname' => 'fake:work'])->assertExitCode(1); // ws-2 still fails the run

        Http::assertNothingSent();
        $this->assertCount(4, self::$ran, 'the tenants are run whether or not reporting is on');
    }
}
