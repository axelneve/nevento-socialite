<?php

declare(strict_types=1);

namespace EventSolutions\NeventoSocialite;

use Illuminate\Support\ServiceProvider;
use Laravel\Socialite\Facades\Socialite;

class NeventoServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
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

        $this->publishes([
            __DIR__.'/../config/nevento-provisioning.php' => config_path('nevento-provisioning.php'),
        ], 'nevento-provisioning-config');
    }

    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/nevento-provisioning.php', 'nevento-provisioning');

        $this->app->singleton(IdentitySyncService::class);
    }
}
