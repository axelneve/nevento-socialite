<?php

declare(strict_types=1);

namespace EventSolutions\NeventoSocialite;

use EventSolutions\NeventoSocialite\Contracts\SyncsWorkspaceRoles;
use EventSolutions\NeventoSocialite\Exceptions\WorkspaceAccessDeniedException;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;
use Laravel\Socialite\Contracts\User as SocialiteUser;

class IdentitySyncService
{
    /** @var array<class-string, bool> */
    private static array $idpIdColumnCache = [];

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

        $user = $this->resolveUser($email, $idpId);

        $user->forceFill([
            'name'           => $name,
            'email'          => $email,
            'idp_id'         => $idpId,
            'workspace_role' => $role,
        ])->save();

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
     * Find the local user for this identity.
     *
     * The IdP id is the stable key; email is not. Matching on email alone meant that
     * changing an address at the IdP created a second local account and orphaned
     * everything attached to the first. Email is still used as a fallback so accounts
     * that predate an idp_id are adopted rather than duplicated.
     */
    private function resolveUser(string $email, mixed $idpId): Model
    {
        $modelClass = config('auth.providers.users.model', \App\Models\User::class);

        if ($idpId !== null && $idpId !== '' && $this->hasIdpIdColumn($modelClass)) {
            // Ordered, not just first(): installs that ran the old email-keyed matching
            // can already hold several rows for one identity. The most recently updated
            // one is the account the person has actually been signing into, so keep
            // them there rather than silently moving them to a dormant duplicate.
            // `nevento:duplicate-identities` finds and neutralises the leftovers.
            $byIdpId = $modelClass::query()
                ->where('idp_id', $idpId)
                ->orderByDesc('updated_at')
                ->orderByDesc($this->keyName($modelClass))
                ->first();

            if ($byIdpId instanceof Model) {
                return $byIdpId;
            }
        }

        return $modelClass::query()->where('email', $email)->first() ?? new $modelClass;
    }

    /**
     * @param  class-string  $modelClass
     */
    private function keyName(string $modelClass): string
    {
        /** @var Model $instance */
        $instance = new $modelClass;

        return $instance->getKeyName();
    }

    /**
     * Cached per class: this runs on every sign-in, and a schema lookup per request is
     * a needless round trip.
     *
     * @param  class-string  $modelClass
     */
    private function hasIdpIdColumn(string $modelClass): bool
    {
        if (! array_key_exists($modelClass, self::$idpIdColumnCache)) {
            /** @var Model $instance */
            $instance = new $modelClass;

            self::$idpIdColumnCache[$modelClass] = Schema::connection($instance->getConnectionName())
                ->hasColumn($instance->getTable(), 'idp_id');
        }

        return self::$idpIdColumnCache[$modelClass];
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
