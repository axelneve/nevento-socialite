<?php

declare(strict_types=1);

namespace EventSolutions\NeventoSocialite;

use EventSolutions\NeventoSocialite\Console\FindDuplicateIdentitiesCommand;
use EventSolutions\NeventoSocialite\System\ReportsToSystem;
use EventSolutions\NeventoSocialite\System\ReportTenantsCommand;
use EventSolutions\NeventoSocialite\System\SystemClient;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\ServiceProvider;
use Laravel\Socialite\Facades\Socialite;

class NeventoServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([FindDuplicateIdentitiesCommand::class, ReportTenantsCommand::class]);
        }

        Socialite::extend('nevento', function ($app) {
            $config = $app['config']->get('services.nevento', []);

            return (new PassportProvider(
                $app['request'],
                (string) ($config['client_id'] ?? ''),
                (string) ($config['client_secret'] ?? ''),
                (string) ($config['redirect'] ?? ''),
                (array)  ($config['guzzle'] ?? [])
            ))
                ->useHostUrl((string) ($config['host'] ?? 'https://idp.nevento.nl'))
                ->useUserInfoUrl((string) ($config['user_info_url'] ?? ''));
        });

        if (config('services.nevento.load_routes', true)) {
            $this->loadRoutesFrom(__DIR__.'/../routes/auth.php');

            if (config('services.nevento.enable_workspace_switching', false)) {
                $this->loadRoutesFrom(__DIR__.'/../routes/workspace.php');
            }
        }

        // Internal tenant-provisioning API (IDP -> this app). Opt-out via config.
        if (config('nevento-provisioning.enabled', true)) {
            $this->loadRoutesFrom(__DIR__.'/../routes/provisioning.php');
        }

        $this->bootSystemReporting();

        $this->publishes([
            __DIR__.'/../config/nevento-provisioning.php' => config_path('nevento-provisioning.php'),
        ], 'nevento-provisioning-config');
    }

    /** Reporting to nevento-system: only when SYSTEM_TOKEN is set, so installing the package changes nothing by itself. */
    private function bootSystemReporting(): void
    {
        if (! $this->app->make(SystemClient::class)->enabled()) {
            return;
        }

        $this->app->make(ReportsToSystem::class)->subscribe($this->app->make(Dispatcher::class));

        if (config('nevento-system.report_tenants', true) && class_exists(\Stancl\Tenancy\Tenancy::class)) {
            $this->callAfterResolving(Schedule::class, fn (Schedule $schedule) => $schedule->command('nevento:system-report-tenants')->hourly()->withoutOverlapping());
        }
    }

    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/nevento-system.php', 'nevento-system');
        $this->app->singleton(SystemClient::class);
        $this->app->singleton(ReportsToSystem::class);

        $this->mergeConfigFrom(__DIR__.'/../config/nevento-provisioning.php', 'nevento-provisioning');

        $this->app->singleton(IdentitySyncService::class);

        // Host apps with their own OAuthController/session-key conventions
        // (different config namespace, different token session keys) can
        // override this binding in their own service provider.
        $this->app->singleton(IdpClient::class);
    }
}
