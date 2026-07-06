<?php

declare(strict_types=1);

namespace EventSolutions\NeventoSocialite\Http\Controllers;

use EventSolutions\NeventoSocialite\IdentitySyncService;
use EventSolutions\NeventoSocialite\NeventoContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;

/**
 * Opt-in: only registered when services.nevento.enable_workspace_switching is true.
 * Switches the active workspace among those the IDP returned for this user
 * (session-only — does not re-hit the IDP, matching how the OAuth client's
 * token already scopes which workspaces are visible).
 */
class WorkspaceSwitchController extends Controller
{
    public function __construct(private readonly IdentitySyncService $sync) {}

    public function switch(string $workspaceId): RedirectResponse
    {
        $workspace = collect(NeventoContext::workspaces())
            ->first(fn (array $w): bool => (string) ($w['id'] ?? '') === $workspaceId);

        if (! is_array($workspace) || empty($workspace['roles'])) {
            abort(403, 'Geen toegang tot deze werkruimte.');
        }

        $roles = array_values(array_filter((array) $workspace['roles'], 'is_string'));

        session([
            'nevento_workspace' => $workspace,
            'nevento_role'      => (string) ($roles[0] ?? 'office'),
            'nevento_roles'     => $roles,
        ]);

        $user = Auth::user();

        if ($user !== null) {
            $this->sync->syncRoles($user, $roles, $workspace);
        }

        return redirect()->to((string) config('services.nevento.redirect_after_switch', '/'));
    }
}
