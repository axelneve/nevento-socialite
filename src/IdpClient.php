<?php

declare(strict_types=1);

namespace EventSolutions\NeventoSocialite;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * Generic HTTP client for calling the Nevento IDP's API using the session's
 * SSO token, with automatic refresh-token retry on 401.
 *
 * This is deliberately independent of IdentitySyncService's session keys —
 * host apps with their own OAuthController/IdentitySyncService (console,
 * myoffice) already write `sso_token`/`sso_refresh_token` to the session and
 * can use the defaults below. Apps using different session key names or a
 * different config namespace can bind their own instance in a service
 * provider, e.g.:
 *
 *   $this->app->singleton(IdpClient::class, fn () => new IdpClient(
 *       baseUrl: config('services.nevento_idp.base_url'),
 *       clientIdConfigKey: 'services.nevento_idp.client_id',
 *       clientSecretConfigKey: 'services.nevento_idp.client_secret',
 *   ));
 */
class IdpClient
{
    private string $baseUrl;

    public function __construct(
        ?string $baseUrl = null,
        private readonly string $tokenSessionKey = 'sso_token',
        private readonly string $refreshTokenSessionKey = 'sso_refresh_token',
        private readonly string $clientIdConfigKey = 'services.nevento.client_id',
        private readonly string $clientSecretConfigKey = 'services.nevento.client_secret',
    ) {
        $this->baseUrl = rtrim($baseUrl ?? (string) config('services.nevento.host', 'https://idp.nevento.nl'), '/');
    }

    public function get(string $path, array $query = []): Response
    {
        return $this->send('GET', $path, $query);
    }

    public function post(string $path, array $data = [], int $timeout = 10): Response
    {
        return $this->send('POST', $path, $data, $timeout);
    }

    public function put(string $path, array $data = []): Response
    {
        return $this->send('PUT', $path, $data);
    }

    public function delete(string $path, array $data = []): Response
    {
        return $this->send('DELETE', $path, $data);
    }

    public function handleErrors(Response $response): array
    {
        if ($response->failed()) {
            $errors = $response->json('errors');
            $message = (string) ($response->json('message') ?? '');

            if (is_array($errors) && $errors !== []) {
                $resolvedMessage = $message !== '' ? $message : 'Ongeldige invoer.';

                return ['_message' => [$resolvedMessage]] + $errors;
            }

            return ['api' => $message !== '' ? $message : 'De IDP gaf een fout terug (status '.$response->status().').'];
        }

        return [];
    }

    /**
     * Fire multiple GET requests concurrently via Http::pool(). Returns an array
     * keyed by the names passed in $requests. Each value is a Response or a
     * Throwable. On 401, refreshes the token once and retries the affected
     * requests.
     *
     * @param  array<string, array{0: string, 1?: array<string, mixed>}>  $requests  ['name' => ['path', $query?]]
     * @return array<string, Response|\Throwable>
     */
    public function concurrentGet(array $requests): array
    {
        $responses = $this->firePool($requests);

        $retryNames = array_keys(array_filter(
            $responses,
            fn (mixed $r): bool => $r instanceof Response && $r->status() === 401,
        ));

        if ($retryNames !== [] && $this->refreshAccessToken()) {
            foreach ($retryNames as $name) {
                $responses[$name] = $this->execute('GET', $this->url($requests[$name][0]), $requests[$name][1] ?? []);
            }
        }

        return $responses;
    }

    /**
     * @param  array<string, array{0: string, 1?: array<string, mixed>}>  $requests
     * @return array<string, Response|\Throwable>
     */
    private function firePool(array $requests): array
    {
        $baseUrl = $this->baseUrl;
        $token = (string) session($this->tokenSessionKey, '');

        return Http::pool(function (Pool $pool) use ($requests, $baseUrl, $token): array {
            return array_map(
                fn (string $name, array $req) => $pool->as($name)
                    ->withToken($token)
                    ->withHeaders(['Accept' => 'application/json'])
                    ->timeout(10)
                    ->get($baseUrl.'/'.ltrim($req[0], '/'), $req[1] ?? []),
                array_keys($requests),
                array_values($requests),
            );
        });
    }

    public function firstErrorMessage(array $errors, ?string $fallback = null): string
    {
        $fallback ??= 'Onbekende fout bij de IDP.';

        foreach ($errors as $field => $value) {
            $prefix = is_string($field) && $field !== '_message' ? "{$field}: " : '';

            if (is_array($value)) {
                foreach ($value as $nested) {
                    if (is_string($nested) && $nested !== '' && ! str_starts_with($nested, 'validation.')) {
                        return $prefix.$nested;
                    }
                }
            }

            if (is_string($value) && $value !== '' && ! str_starts_with($value, 'validation.')) {
                return $prefix.$value;
            }
        }

        return $fallback;
    }

    private function http(int $timeout = 10): PendingRequest
    {
        return Http::withToken((string) session($this->tokenSessionKey, ''))
            ->withHeaders(['Accept' => 'application/json'])
            ->timeout($timeout);
    }

    private function send(string $method, string $path, array $payload = [], int $timeout = 10): Response
    {
        $url = $this->url($path);
        $response = $this->execute($method, $url, $payload, $timeout);

        if ($response->status() !== 401) {
            return $response;
        }

        if (! $this->refreshAccessToken()) {
            return $response;
        }

        return $this->execute($method, $url, $payload, $timeout);
    }

    private function execute(string $method, string $url, array $payload = [], int $timeout = 10): Response
    {
        return match (strtoupper($method)) {
            'GET' => $this->http($timeout)->get($url, $payload),
            'POST' => $this->http($timeout)->post($url, $payload),
            'PUT' => $this->http($timeout)->put($url, $payload),
            'DELETE' => $this->http($timeout)->delete($url, $payload),
            default => throw new \InvalidArgumentException('Unsupported IdP method: '.$method),
        };
    }

    private function refreshAccessToken(): bool
    {
        $refreshToken = trim((string) session($this->refreshTokenSessionKey, ''));
        $clientId = trim((string) config($this->clientIdConfigKey, ''));

        if ($refreshToken === '' || $clientId === '') {
            return false;
        }

        $payload = [
            'grant_type' => 'refresh_token',
            'refresh_token' => $refreshToken,
            'client_id' => $clientId,
        ];

        $clientSecret = trim((string) config($this->clientSecretConfigKey, ''));

        if ($clientSecret !== '') {
            $payload['client_secret'] = $clientSecret;
        }

        $response = Http::asForm()
            ->withHeaders(['Accept' => 'application/json'])
            ->timeout(10)
            ->post($this->url('oauth/token'), $payload);

        if ($response->failed()) {
            return false;
        }

        $accessToken = trim((string) $response->json('access_token', ''));

        if ($accessToken === '') {
            return false;
        }

        $nextRefreshToken = trim((string) $response->json('refresh_token', $refreshToken));

        session([
            $this->tokenSessionKey => $accessToken,
            $this->refreshTokenSessionKey => $nextRefreshToken !== '' ? $nextRefreshToken : $refreshToken,
        ]);

        return true;
    }

    private function url(string $path): string
    {
        return $this->baseUrl.'/'.ltrim($path, '/');
    }
}
