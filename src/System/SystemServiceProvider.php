<?php

declare(strict_types=1);

namespace EventSolutions\NeventoSocialite\System;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider;

/**
 * Reporting to nevento-system, and nothing else. The package's main provider registers it, so apps that use the whole
 * package get it for free. An app that must not get the rest (nevento-idp has no use for OAuth client routes or the
 * Socialite driver) turns off discovery of the package and registers only this provider:
 *
 *   composer.json  "extra": {"laravel": {"dont-discover": ["eventsolutions/nevento-socialite"]}}
 *   bootstrap/providers.php  EventSolutions\NeventoSocialite\System\SystemServiceProvider::class
 *
 * Off until SYSTEM_TOKEN is set, so installing it changes nothing by itself.
 */
class SystemServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../../config/nevento-system.php', 'nevento-system');
        $this->app->singleton(SystemClient::class);
        $this->app->singleton(ReportsToSystem::class);
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([ReportTenantsCommand::class, TenantsRunCommand::class]);
        }

        // The report button: `<x-nevento::report-button />` and the route it posts to; the shared app shell (`<x-nevento::shell>`, the
        // navigation items, the marks) lives in the same folder, with its words in lang/ (`nevento::shell.*`).
        Blade::anonymousComponentPath(__DIR__.'/../../resources/views/components', 'nevento');
        $this->loadTranslationsFrom(__DIR__.'/../../lang', 'nevento');
        $this->loadRoutesFrom(__DIR__.'/../../routes/system.php');

        if (! $this->app->make(SystemClient::class)->enabled()) {
            return;
        }

        $this->app->make(ReportsToSystem::class)->subscribe($this->app->make(Dispatcher::class));

        if (config('nevento-system.report_tenants', true) && class_exists(\Stancl\Tenancy\Tenancy::class)) {
            $this->callAfterResolving(Schedule::class, fn (Schedule $schedule) => $schedule->command('nevento:system-report-tenants')->hourly()->withoutOverlapping());
        }
    }
}
