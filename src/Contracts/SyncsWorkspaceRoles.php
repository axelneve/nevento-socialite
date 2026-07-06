<?php

declare(strict_types=1);

namespace EventSolutions\NeventoSocialite\Contracts;

use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Bind an implementation of this contract in the host app's container to mirror
 * IDP roles into the app's own permission system (e.g. Spatie group roles).
 *
 * Called by IdentitySyncService after login, and again by WorkspaceSwitchController
 * after a workspace switch. Optional — if nothing is bound, it is simply skipped.
 */
interface SyncsWorkspaceRoles
{
    /**
     * @param  array<int, string>  $roles
     * @param  array<string, mixed>  $workspace
     */
    public function sync(Authenticatable $user, array $roles, array $workspace): void;
}
