<?php

declare(strict_types=1);

namespace EventSolutions\NeventoSocialite;

use EventSolutions\NeventoSocialite\Contracts\SyncsWorkspaceRoles;
use EventSolutions\NeventoSocialite\Exceptions\WorkspaceAccessDeniedException;
use Illuminate\Contracts\Auth\Authenticatable;
use Laravel\Socialite\Contracts\User as SocialiteUser;

class IdentitySyncService
{
    /**
     * Sync IDP user data to the local User model and session.
     *
     * The IDP is the authority on which workspace this token belongs to —
     * the OAuth client's workspace_id enforces it server-side.
     *
     * @throws WorkspaceAccessDeniedException
     */
    public function sync(array $rawUser, SocialiteUser $oauthUser): Authenticatable
    {
        // The IDP returns the single workspace the OAuth client is scoped to
        // (for multi-workspace apps, `workspaces` also carries the full list).
        $workspace = $rawUser['workspace'] ?? null;

        if (! is_array($workspace) || empty($workspace['roles'])) {
            throw new WorkspaceAccessDeniedException();
        }

        $roles = array_values(array_filter((array) $workspace['roles'], 'is_string'));
        $role  = (string) ($roles[0] ?? 'office');
        $email = (string) ($oauthUser->getEmail() ?: data_get($rawUser, 'email', ''));
        $name  = (string) ($oauthUser->getName() ?: data_get($rawUser, 'name', ''));
        $idpId = $oauthUser->getId() ?: data_get($rawUser, 'id');

        $isSuperadmin = (bool) ($rawUser['is_superadmin'] ?? false);
        $workspaces   = array_values(array_filter((array) ($rawUser['workspaces'] ?? []), 'is_array'));
        $license      = $workspace['license'] ?? $rawUser['license'] ?? null;

        $modelClass = config('auth.providers.users.model', \App\Models\User::class);

        /** @var \Illuminate\Database\Eloquent\Model $user */
        $user = $modelClass::updateOrCreate(
            ['email' => $email],
            [
                'name'           => $name,
                'idp_id'         => $idpId,
                'workspace_role' => $role,
            ]
        );

        session([
            'nevento_user'             => ['id' => $idpId, 'name' => $name, 'email' => $email],
            'nevento_workspace'        => $workspace,
            'nevento_workspaces'       => $workspaces,
            'nevento_role'             => $role,
            'nevento_roles'            => $roles,
            'nevento_superadmin'       => $isSuperadmin,
            'nevento_license'          => is_array($license) ? $license : null,
            'nevento_token_expires_at' => is_numeric($oauthUser->expiresIn ?? null)
                ? now()->addSeconds((int) $oauthUser->expiresIn)->timestamp
                : null,
        ]);

        $this->syncRoles($user, $roles, $workspace);

        return $user;
    }

    /**
     * Calls the host app's bound SyncsWorkspaceRoles implementation, if any.
     * Public so WorkspaceSwitchController can re-run it after a workspace switch.
     *
     * @param  array<int, string>  $roles
     * @param  array<string, mixed>  $workspace
     */
    public function syncRoles(Authenticatable $user, array $roles, array $workspace): void
    {
        if (app()->bound(SyncsWorkspaceRoles::class)) {
            app(SyncsWorkspaceRoles::class)->sync($user, $roles, $workspace);
        }
    }
}
