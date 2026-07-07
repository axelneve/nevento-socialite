<?php

declare(strict_types=1);

namespace EventSolutions\NeventoSocialite\Http\Controllers;

use EventSolutions\NeventoSocialite\Contracts\TenantProvisioner;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * Thin transport layer for the tenant-provisioning contract. Auth is handled by
 * the VerifyProvisioningSecret middleware; app-specific behaviour is delegated to
 * the host app's bound TenantProvisioner. The provisioner is resolved lazily so
 * the health endpoint works even before one is bound.
 */
class ProvisioningController extends Controller
{
    /**
     * Reaching here means the secret was valid (middleware) and the app is up.
     * The IDP "Test connection" button relies on this.
     */
    public function health(): JsonResponse
    {
        return response()->json([
            'status' => 'ok',
            'message' => (string) config('app.name', 'App').' provisioning endpoint healthy',
        ]);
    }

    public function install(Request $request): JsonResponse
    {
        $data = $request->validate([
            'tenant_key' => ['required', 'string', 'max:100'],
            'workspace_id' => ['required', 'integer'],
            'workspace_slug' => ['required', 'string', 'max:120'],
            'workspace_name' => ['required', 'string', 'max:255'],
            'seed_admin_user' => ['nullable', 'array'],
            'seed_admin_user.id' => ['nullable', 'integer'],
            'seed_admin_user.email' => ['nullable', 'email'],
            'seed_admin_user.display_name' => ['nullable', 'string', 'max:255'],
        ]);

        return response()->json($this->provisioner()->install($data + $request->all()));
    }

    public function suspend(Request $request): JsonResponse
    {
        return response()->json($this->provisioner()->suspend($this->tenantScopePayload($request)));
    }

    public function unsuspend(Request $request): JsonResponse
    {
        return response()->json($this->provisioner()->unsuspend($this->tenantScopePayload($request)));
    }

    public function uninstall(Request $request): JsonResponse
    {
        return response()->json($this->provisioner()->uninstall($this->tenantScopePayload($request)));
    }

    public function status(string $tenantKey): JsonResponse
    {
        return response()->json($this->provisioner()->status($tenantKey));
    }

    /**
     * @return array<string, mixed>
     */
    private function tenantScopePayload(Request $request): array
    {
        $request->validate([
            'tenant_key' => ['required', 'string', 'max:100'],
            'workspace_id' => ['nullable', 'integer'],
        ]);

        return $request->all();
    }

    private function provisioner(): TenantProvisioner
    {
        return app(TenantProvisioner::class);
    }
}
