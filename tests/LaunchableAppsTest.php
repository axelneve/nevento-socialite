<?php

declare(strict_types=1);

namespace EventSolutions\NeventoSocialite\Tests;

use EventSolutions\NeventoSocialite\Shell\LaunchableApps;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

class LaunchableAppsTest extends TestCase
{
    private function apps(): LaunchableApps
    {
        return app(LaunchableApps::class);
    }

    public function test_it_lists_the_apps_marks_the_current_one_by_host_and_keeps_them_in_the_session(): void
    {
        Http::fake(['idp.test/*' => Http::response(['data' => [
            ['id' => 1, 'name' => 'myOffice', 'slug' => 'myoffice', 'launch_url' => 'https://myoffice.nevento.nl/'],
            ['id' => 2, 'name' => 'Console', 'slug' => 'console', 'launch_url' => 'https://console.nevento.nl'],
            ['id' => 3, 'name' => 'No url', 'slug' => 'x', 'launch_url' => null],
        ]])]);
        session(['sso_token' => 'tok']);
        $this->app['request']->headers->set('host', 'console.nevento.nl');
        $this->app->instance('request', \Illuminate\Http\Request::create('https://console.nevento.nl/'));

        $first = $this->apps()->forWorkspace(7);
        $second = $this->apps()->forWorkspace(7);

        $this->assertSame([['myOffice', 'https://myoffice.nevento.nl/auth/redirect', false], ['Console', 'https://console.nevento.nl/auth/redirect', true]], array_map(fn ($a) => [$a['name'], $a['url'], $a['active']], $first));
        $this->assertSame($first, $second);
        Http::assertSentCount(1);
        Http::assertSent(fn (Request $r) => str_ends_with($r->url(), 'api/v1/workspaces/7/launchable-apps') && $r->hasHeader('Authorization', 'Bearer tok'));
    }

    public function test_an_app_with_only_its_own_idp_config_calls_that_idp_and_an_app_with_none_calls_nothing(): void
    {
        Http::fake(['*' => Http::response(['data' => []])]);
        session(['sso_token' => 'tok']);
        config(['services.nevento.host' => null, 'services.nevento_idp.base_url' => 'https://nevento-idp.test']);

        $this->apps()->forWorkspace(7);
        Http::assertSent(fn (Request $r) => str_starts_with($r->url(), 'https://nevento-idp.test/'));

        // No IDP configured at all: never the package's production default.
        session()->forget('nevento_launchable_apps');
        config(['services.nevento_idp.base_url' => null]);
        Http::fake(['*' => Http::response(['data' => []])]);

        $this->assertSame([], $this->apps()->forWorkspace(8));
        Http::assertNothingSent();
    }

    public function test_a_failing_idp_or_a_missing_token_gives_an_empty_list_and_never_throws(): void
    {
        Http::fake(['idp.test/*' => Http::response('', 500)]);

        $this->assertSame([], $this->apps()->forWorkspace(7), 'no SSO token in the session');

        session(['sso_token' => 'tok']);
        $this->assertSame([], $this->apps()->forWorkspace(7));
        $this->assertSame([], $this->apps()->forWorkspace(null));
    }
}
