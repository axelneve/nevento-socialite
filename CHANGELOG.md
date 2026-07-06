# Changelog

All notable changes to this package will be documented here.

## [1.1.0] — 2026-07-06

Additive only — fully backward compatible with 1.0.x for existing single-workspace
integrations (e.g. VOER). No existing session keys, method signatures, or DB writes
changed.

### Added
- Multi-workspace support: `IdentitySyncService::sync()` now also reads `is_superadmin`
  and the full `workspaces` list from the IDP payload (when present) and writes
  `nevento_superadmin`, `nevento_workspaces`, `nevento_roles` (full roles array)
  session keys alongside the existing ones.
- `NeventoContext`: static accessor for all `nevento_*` session state —
  `user()`, `workspace()`, `workspaces()`, `role()`, `roles()`, `isSuperadmin()`,
  `hasRole()`, `hasAnyRole()`, `license()`, `isLicenseActive()`, `tokenExpiresAt()`.
- `Contracts\SyncsWorkspaceRoles`: optional contract a host app can bind in its
  container to mirror IDP roles into its own permission system (e.g. Spatie group
  roles). Called after login and after a workspace switch; skipped entirely if
  nothing is bound.
- `WorkspaceSwitchController` + `nevento.workspace.switch` route — opt-in via
  `services.nevento.enable_workspace_switching` (default `false`). Switches the
  active workspace among those the IDP returned, session-only (no re-hit of the IDP).
- `RequireWorkspaceAccess` middleware: role checks now match against the full roles
  array (previously only the first role) and bypass entirely for superadmins — a
  strict superset of the old check, so nobody who previously passed can be blocked.
- Forward-compatible license/entitlement passthrough: if the IDP payload includes a
  `license` object on the workspace (not yet the case in production — see IDP-side
  backlog), it's captured as `nevento_license` and exposed via
  `NeventoContext::license()` / `isLicenseActive()` (returns `null` — "unknown" —
  until the IDP actually sends it).

## [1.0.0] — 2026-06-27

### Added
- `PassportProvider`: Socialite driver for Nevento Passport OAuth2 endpoints (`/oauth/authorize`, `/oauth/token`, `/api/user`)
- `IdentitySyncService`: upserts the local `User` model from the IDP response, writes `nevento_*` session keys
- `OAuthController`: handles redirect, callback (with `InvalidStateException` + `ClientException` recovery), and logout
- `RequireWorkspaceAccess`: middleware with optional role arguments (`nevento.workspace:admin,office`)
- `NeventoServiceProvider`: auto-discovered, registers the Socialite driver and loads package routes
- Auto-registered routes: `nevento.redirect`, `nevento.callback`, `nevento.logout`
- `WorkspaceAccessDeniedException`: thrown when the IDP returns no workspace (user not assigned to this site)
- PKCE support (`use_pkce` config, defaults to `true`)
- Stateless mode support (`stateless` config)
- Custom error view via `nevento::auth.error` Blade namespace
- IDP SSO logout redirect via `services.nevento.logout_url`

### Architecture
- Each site uses a **dedicated OAuth client** in the IDP, carrying `workspace_id` + `app_id`
- The IDP filters the `/api/user` response to the client's workspace — no per-site slug discriminator needed on the client side
