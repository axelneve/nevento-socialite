<?php

declare(strict_types=1);

namespace EventSolutions\NeventoSocialite;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * An app's side of the IDP's people register API (nevento-idp,
 * `.claude/specs/personen-register.md`). It authenticates AS THE APP with a
 * `client_credentials` token, not as a user, so it works from queued jobs and the daily
 * reconcile as well as from a request. Separate from {@see IdpClient}, which is bound to
 * the signed-in user's session token.
 *
 * The IDP decides what this app may do: any listed, active app reads (with its own field
 * list), and only the workspace's managing app writes. A refused call throws
 * {@see PeopleClientException}.
 *
 * Apps with another config namespace pass their own keys, like IdpClient:
 *
 *   $this->app->singleton(PeopleClient::class, fn () => new PeopleClient(
 *       baseUrl: config('services.nevento_idp.base_url'),
 *       clientIdConfigKey: 'services.nevento_idp.client_id',
 *       clientSecretConfigKey: 'services.nevento_idp.client_secret',
 *   ));
 */
class PeopleClient
{
    private const SCOPE = 'people:read people:write';

    private const EXPIRY_SKEW_SECONDS = 30;

    private const MANAGING_APP_TTL_SECONDS = 300;

    private string $baseUrl;

    public function __construct(
        ?string $baseUrl = null,
        private readonly string $clientIdConfigKey = 'services.nevento.client_id',
        private readonly string $clientSecretConfigKey = 'services.nevento.client_secret',
    ) {
        $this->baseUrl = rtrim($baseUrl ?? (string) config('services.nevento.host', 'https://idp.nevento.nl'), '/');
    }

    /**
     * Every person of the workspace this app may see, all pages. Pass `$updatedSince` for
     * the daily reconcile. Fields are filtered by the IDP per app, so never assume one exists.
     *
     * @return list<array<string, mixed>>
     */
    public function list(int $workspaceId, ?\DateTimeInterface $updatedSince = null): array
    {
        $people = [];
        $page = 1;

        do {
            $response = $this->send(fn (PendingRequest $request) => $request->get($this->url($workspaceId), array_filter([
                'updated_since' => $updatedSince?->format(\DATE_ATOM),
                'per_page' => 200,
                'page' => $page,
            ])));

            $people = [...$people, ...(array) $response->json('data', [])];
            $last = (int) $response->json('meta.last_page', 1);
        } while ($page++ < $last);

        return $people;
    }

    /**
     * The slug of the app that manages the workspace's people (`myoffice` else `rento`), or null
     * when none is active. The rule lives in the IDP, so an app asks instead of repeating it: its
     * form is editable when this is the app itself and read-only otherwise. Cached for a few
     * minutes, because a form asks on every page view and the answer changes only when an app is
     * installed or removed.
     */
    public function managingApp(int $workspaceId): ?string
    {
        return Cache::remember(
            'nevento.people.managing_app.'.md5($this->baseUrl.$workspaceId),
            self::MANAGING_APP_TTL_SECONDS,
            fn (): ?string => ($slug = $this->send(fn (PendingRequest $request) => $request->get($this->url($workspaceId, 'managing-app')))->json('data.slug')) !== null ? (string) $slug : null,
        );
    }

    /**
     * Create a person. Only the workspace's managing app may.
     *
     * @param  array<string, mixed>  $data  name, email (required, unique per workspace), phone, address fields, active
     * @return array<string, mixed>
     */
    public function create(int $workspaceId, array $data): array
    {
        return (array) $this->send(fn (PendingRequest $request) => $request->post($this->url($workspaceId), $data))->json('data');
    }

    /**
     * Change a person by uuid. Only the workspace's managing app may.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function update(int $workspaceId, string $uuid, array $data): array
    {
        return (array) $this->send(fn (PendingRequest $request) => $request->patch($this->url($workspaceId, $uuid), $data))->json('data');
    }

    private function url(int $workspaceId, ?string $uuid = null): string
    {
        return "{$this->baseUrl}/api/v1/workspaces/{$workspaceId}/people".($uuid !== null ? "/{$uuid}" : '');
    }

    /**
     * @param  callable(PendingRequest): Response  $call
     */
    private function send(callable $call): Response
    {
        try {
            $response = $call($this->authenticated());

            // A token revoked or expired early: ask for a fresh one once.
            if ($response->status() === 401) {
                Cache::forget($this->tokenCacheKey());
                $response = $call($this->authenticated());
            }
        } catch (ConnectionException $e) {
            throw new PeopleClientException('Could not reach the IDP: '.$e->getMessage(), 0, [], $e);
        }

        if ($response->failed()) {
            throw new PeopleClientException(
                (string) ($response->json('message') ?? 'The IDP answered '.$response->status().'.'),
                $response->status(),
                (array) $response->json('errors', []),
            );
        }

        return $response;
    }

    private function authenticated(): PendingRequest
    {
        return Http::withToken($this->appToken())->acceptJson()->timeout(15);
    }

    private function appToken(): string
    {
        $cached = Cache::get($this->tokenCacheKey());
        if (is_string($cached) && $cached !== '') {
            return $cached;
        }

        try {
            $response = Http::asForm()->acceptJson()->timeout(10)->post("{$this->baseUrl}/oauth/token", [
                'grant_type' => 'client_credentials',
                'client_id' => config($this->clientIdConfigKey),
                'client_secret' => config($this->clientSecretConfigKey),
                'scope' => self::SCOPE,
            ]);
        } catch (ConnectionException $e) {
            throw new PeopleClientException('Could not reach the IDP: '.$e->getMessage(), 0, [], $e);
        }

        $token = (string) $response->json('access_token');
        if ($response->failed() || $token === '') {
            throw new PeopleClientException('The IDP gave no app token: '.(string) ($response->json('error_description') ?? $response->status()), $response->status());
        }

        $ttl = (int) $response->json('expires_in', 0) - self::EXPIRY_SKEW_SECONDS;
        if ($ttl > 0) {
            Cache::put($this->tokenCacheKey(), $token, $ttl);
        }

        return $token;
    }

    private function tokenCacheKey(): string
    {
        return 'nevento.people.app_token.'.md5($this->baseUrl.config($this->clientIdConfigKey));
    }
}
