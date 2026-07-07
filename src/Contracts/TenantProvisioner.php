<?php

declare(strict_types=1);

namespace EventSolutions\NeventoSocialite\Contracts;

/**
 * Implemented by a client app (e.g. Rento) to handle the app-specific side of
 * tenant provisioning. The package owns the transport, auth and validation; the
 * host app supplies the actual create/suspend/status behaviour.
 *
 * Bind an implementation in the container:
 *   $this->app->bind(TenantProvisioner::class, YourProvisioner::class);
 */
interface TenantProvisioner
{
    /**
     * Create (or idempotently ensure) the tenant for this workspace+app.
     *
     * @param  array<string, mixed>  $payload  tenant_key, workspace_id, workspace_slug,
     *                                          workspace_name, seed_admin_user, ...
     * @return array<string, mixed>  e.g. ['status' => 'ready', 'app_tenant_id' => '...', 'db_reference' => '...']
     */
    public function install(array $payload): array;

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function suspend(array $payload): array;

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function unsuspend(array $payload): array;

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function uninstall(array $payload): array;

    /**
     * Report whether the tenant actually exists on this app. The IDP polls this
     * to verify an install truly landed before trusting it — so this must reflect
     * real state, never optimistic assumptions.
     *
     * @return array{exists: bool, status?: string}
     */
    public function status(string $tenantKey): array;
}
