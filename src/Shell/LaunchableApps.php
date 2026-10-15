<?php

declare(strict_types=1);

namespace EventSolutions\NeventoSocialite\Shell;

use EventSolutions\NeventoSocialite\IdpClient;
use Throwable;

/**
 * The apps a person can open for a workspace, for the app switcher in the shared shell: the IDP's `launchable-apps` endpoint, which it
 * offers to every client app for exactly this purpose. Kept in the session for ten minutes so a page never waits on the IDP, and a failing
 * IDP means an empty list (the switcher is simply not shown), never a broken page.
 */
class LaunchableApps
{
    private const TTL_SECONDS = 600;

    public function __construct(private readonly ?IdpClient $idp = null) {}

    /**
     * The client for THIS app's IDP: `services.nevento.host` where the app has the package's own config (console), or the app's own
     * `services.nevento_idp.*` (myOffice, rento). Never a default: the package's own default is the production IDP, and a development
     * machine would send a local token there (a "token signature mismatch" in production's Sentry). Without any config there is no list.
     */
    private function client(): ?IdpClient
    {
        if (filled(config('services.nevento.host'))) {
            return $this->idp ?? app(IdpClient::class);
        }

        if (filled(config('services.nevento_idp.base_url'))) {
            return new IdpClient(
                baseUrl: (string) config('services.nevento_idp.base_url'),
                clientIdConfigKey: 'services.nevento_idp.client_id',
                clientSecretConfigKey: 'services.nevento_idp.client_secret',
            );
        }

        return null;
    }

    /**
     * @return list<array{id: int, name: string, logo_url: ?string, url: string, active: bool, external: bool}>
     */
    public function forWorkspace(string|int|null $workspaceId): array
    {
        $client = $this->client();

        if ($client === null || $workspaceId === null || $workspaceId === '' || ! session()->has('sso_token')) {
            return [];
        }

        $cached = session('nevento_launchable_apps');

        if (is_array($cached) && ($cached['workspace'] ?? null) === (string) $workspaceId && ($cached['at'] ?? 0) > time() - self::TTL_SECONDS) {
            return $this->mark((array) $cached['apps']);
        }

        try {
            $response = $client->get("api/v1/workspaces/{$workspaceId}/launchable-apps");
            $apps = $response->successful() ? (array) $response->json('data', []) : [];
        } catch (Throwable) {
            $apps = [];
        }

        $apps = array_values(array_filter(array_map(fn ($app) => is_array($app) && filled($app['launch_url'] ?? null) ? [
            'id' => (int) ($app['id'] ?? 0),
            'name' => (string) ($app['name'] ?? ''),
            'logo_url' => $app['logo_url'] ?? null,
            'launch_url' => rtrim((string) $app['launch_url'], '/'),
        ] : null, $apps)));

        session(['nevento_launchable_apps' => ['workspace' => (string) $workspaceId, 'at' => time(), 'apps' => $apps]]);

        return $this->mark($apps);
    }

    /**
     * The shell's item shape. The current app is the one whose address has this request's host: the same rule in every app, no per-app id.
     *
     * @param  list<array<string, mixed>>  $apps
     * @return list<array{id: int, name: string, logo_url: ?string, url: string, active: bool, external: bool}>
     */
    private function mark(array $apps): array
    {
        $host = request()->getHost();

        return array_map(fn (array $app) => [
            'id' => (int) $app['id'],
            'name' => (string) $app['name'],
            'logo_url' => $app['logo_url'] ?? null,
            'url' => $app['launch_url'].'/auth/redirect',
            'active' => parse_url((string) $app['launch_url'], PHP_URL_HOST) === $host,
            'external' => true,
        ], $apps);
    }
}
