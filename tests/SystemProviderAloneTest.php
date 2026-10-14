<?php

declare(strict_types=1);

namespace EventSolutions\NeventoSocialite\Tests;

use EventSolutions\NeventoSocialite\System\SystemServiceProvider;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Route;
use Orchestra\Testbench\TestCase as Orchestra;

/** An app like nevento-idp registers only the reporting provider: it must get the commands and none of the rest. */
class SystemProviderAloneTest extends Orchestra
{
    /** @return array<int, class-string> */
    protected function getPackageProviders($app): array
    {
        return [SystemServiceProvider::class];
    }

    public function test_only_the_reporting_part_is_installed(): void
    {
        $commands = array_keys(Artisan::all());

        $this->assertContains('nevento:tenants-run', $commands);
        $this->assertContains('nevento:system-report-tenants', $commands);
        $this->assertNotContains('nevento:duplicate-identities', $commands);

        $uris = collect(Route::getRoutes()->getRoutes())->map->uri()->implode(' ');
        $this->assertStringNotContainsString('auth/', $uris, 'no OAuth client routes');
        $this->assertStringNotContainsString('internal/', $uris, 'no provisioning routes');

        $this->assertNotNull(config('nevento-system'), 'its config is merged');
    }
}
