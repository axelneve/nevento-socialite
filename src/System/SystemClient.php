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

    /**
     * A bug report or feature request from a user. Unlike the rest it may carry a screenshot, so it gets a longer
     * timeout; the caller tells the user whether it arrived.
     *
     * @param  array<string, mixed>  $report
     */
    public function report(array $report): bool
    {
        return $this->post('reports', $report, 15);
    }

    /**
     * The reports one person sent from this app (newest first, at most 50, without the owner's notes), for a "my reports"
     * widget. Null when the system cannot be reached; callers should cache it and hide the widget on null.
     *
     * @return list<array{id: int, type: string, summary: string, status: string, created_at: string, resolved_at: string|null}>|null
     */
    public function myReports(string $reporter): ?array
    {
        if (! $this->enabled() || (self::$backoffUntil !== null && self::$backoffUntil > time())) {
            return null;
        }

        try {
            $response = Http::withToken((string) config('nevento-system.token'))
                ->acceptJson()
                ->connectTimeout(1)
                ->timeout(3)
                ->get(rtrim((string) config('nevento-system.url'), '/').'/api/v1/reports', ['reporter' => $reporter]);

            if ($response->successful()) {
                return array_values((array) $response->json('data', []));
            }

            $this->backOff("HTTP {$response->status()}");
        } catch (Throwable $e) {
            $this->backOff($e->getMessage());
        }

        return null;
    }

    /** Forget a backoff, for tests and for long-running workers that should retry sooner. */
    public static function resetBackoff(): void
    {
        self::$backoffUntil = null;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function post(string $path, array $payload, ?int $timeout = null): bool
    {
        if (! $this->enabled() || (self::$backoffUntil !== null && self::$backoffUntil > time())) {
            return false;
        }

        // A web request must not wait for the system, so it gets three seconds. A scheduled task has already finished and its
        // process is not waited on by anyone: it gets ten seconds and one retry, because on a busy server (all the hourly tasks
        // start at :00) three seconds loses the heartbeat and the monitor then reports a task that did run as not running.
        $background = app()->runningInConsole();
        $timeout ??= $background ? 10 : 3;
        $attempts = $background ? 2 : 1;

        for ($attempt = 1; $attempt <= $attempts; $attempt++) {
            try {
                $response = Http::withToken((string) config('nevento-system.token'))
                    ->acceptJson()
                    ->connectTimeout($background ? 3 : 1)
                    ->timeout($timeout)
                    ->post(rtrim((string) config('nevento-system.url'), '/').'/api/v1/'.$path, array_filter($payload, fn ($value) => $value !== null));

                if ($response->successful()) {
                    return true;
                }

                // An answer, but not a good one: the system is there and says no; asking again will not change that.
                $this->backOff("HTTP {$response->status()}");

                return false;
            } catch (Throwable $e) {
                if ($attempt === $attempts) {
                    $this->backOff($e->getMessage());
                }
            }
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
