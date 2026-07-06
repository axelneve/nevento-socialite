<?php

declare(strict_types=1);

namespace EventSolutions\NeventoSocialite;

/**
 * Read-only accessor for the nevento_* session state written by IdentitySyncService.
 * Gives host apps a stable API instead of reaching into session() directly.
 */
class NeventoContext
{
    /** @return array{id: mixed, name: string, email: string}|null */
    public static function user(): ?array
    {
        return session('nevento_user');
    }

    /** @return array<string, mixed>|null */
    public static function workspace(): ?array
    {
        return session('nevento_workspace');
    }

    /**
     * All workspaces the current user belongs to, if the IDP returned a list
     * (multi-workspace apps only — empty for single-workspace client-site setups).
     *
     * @return array<int, array<string, mixed>>
     */
    public static function workspaces(): array
    {
        return (array) session('nevento_workspaces', []);
    }

    /** First role in the current workspace, e.g. 'admin'. */
    public static function role(): ?string
    {
        return session('nevento_role');
    }

    /** @return array<int, string> All roles in the current workspace. */
    public static function roles(): array
    {
        return (array) session('nevento_roles', []);
    }

    public static function isSuperadmin(): bool
    {
        return (bool) session('nevento_superadmin', false);
    }

    public static function hasRole(string $role): bool
    {
        return self::isSuperadmin() || in_array($role, self::roles(), true);
    }

    /** @param  array<int, string>  $roles */
    public static function hasAnyRole(array $roles): bool
    {
        return self::isSuperadmin() || array_intersect($roles, self::roles()) !== [];
    }

    /**
     * License/entitlement data for the current workspace+app, if the IDP reports it.
     * Currently null in practice — the IDP's /api/user does not yet expose license
     * state to client apps. Reading this now costs nothing and makes the package
     * forward-compatible for when that lands.
     *
     * @return array<string, mixed>|null
     */
    public static function license(): ?array
    {
        return session('nevento_license');
    }

    /**
     * Null means the IDP did not report license state (current default behavior) —
     * treat null as "unknown", not as "inactive".
     */
    public static function isLicenseActive(): ?bool
    {
        $license = self::license();

        if ($license === null) {
            return null;
        }

        return in_array($license['status'] ?? null, ['active', 'trial'], true);
    }

    public static function tokenExpiresAt(): ?int
    {
        $value = session('nevento_token_expires_at');

        return is_int($value) ? $value : null;
    }
}
