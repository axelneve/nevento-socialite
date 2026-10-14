<?php

declare(strict_types=1);

namespace EventSolutions\NeventoSocialite\System;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * An app's side of the nevento-system ingest API (see nevento-system `.claude/specs/system-monitoring.md`, "Ingest API v1").
 *
 * Reporting must never hurt the app: short timeouts, every error swallowed, and after a failure no further attempt for a
 * while in this process, so a system that is down costs one slow call, not one per task.
 */
class SystemClient
{
    private const BACKOFF_SECONDS = 60;

    private static ?int $backoffUntil = null;

    public function enabled(): bool
    {
        return filled(config('nevento-system.token'));
    }

    public function heartbeat(string $task, string $status, ?string $tenant = null, ?int $durationMs = null, ?string $message = null, ?int $expectedEveryMinutes = null): bool
    {
        return $this->post('heartbeats', [
            'task' => $task,
            'tenant' => $tenant,
            'status' => $status,
            'duration_ms' => $durationMs,
            'message' => $message,
            'expected_every_minutes' => $expectedEveryMinutes,
        ]);
    }

    public function signal(string $kind, ?string $tenant = null, ?string $message = null): bool
    {
        return $this->post('signals', ['kind' => $kind, 'tenant' => $tenant, 'message' => $message]);
    }

    /**
     * @param  list<array{tenant: string, database_ok: bool, pending_migrations?: int, last_activity_at?: string|null}>  $tenants
     */
    public function tenants(array $tenants): bool
    {
        return $this->post('tenants', ['tenants' => $tenants]);
    }

    /** Forget a backoff, for tests and for long-running workers that should retry sooner. */
    public static function resetBackoff(): void
    {
        self::$backoffUntil = null;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function post(string $path, array $payload): bool
    {
        if (! $this->enabled() || (self::$backoffUntil !== null && self::$backoffUntil > time())) {
            return false;
        }

        try {
            $response = Http::withToken((string) config('nevento-system.token'))
                ->acceptJson()
                ->connectTimeout(1)
                ->timeout(3)
                ->post(rtrim((string) config('nevento-system.url'), '/').'/api/v1/'.$path, array_filter($payload, fn ($value) => $value !== null));

            if ($response->successful()) {
                return true;
            }

            $this->backOff("HTTP {$response->status()}");
        } catch (Throwable $e) {
            $this->backOff($e->getMessage());
        }

        return false;
    }

    private function backOff(string $reason): void
    {
        self::$backoffUntil = time() + self::BACKOFF_SECONDS;

        try {
            Log::warning('nevento-system: rapportage mislukt, een minuut niet opnieuw geprobeerd: '.$reason);
        } catch (Throwable) {
            // Not even logging may break the app.
        }
    }
}
