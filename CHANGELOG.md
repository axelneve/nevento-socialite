# Changelog

All notable changes to this package will be documented here.

## [1.4.0] — 2026-09-08

### Added
- **Back-channel single logout.** `POST /internal/sessions/logout` ends this app's
  sessions when a user signs out of the IDP. Previously an IDP logout left every app
  session running until it expired on its own. Rides the existing provisioning channel
  (per-app Bearer secret); the IDP's signed `logout_token` is accepted alongside it so
  an app can verify the call against the IDP's JWKS. Requires the database session
  driver to be able to end sessions remotely.
- A test suite (orchestra/testbench). The package had none.

### Fixed
- **A failed OAuth `state` check no longer falls back to `stateless()`.** That turned
  the CSRF protection into "try again without it". A mismatch now restarts the flow
  once, then reports the error rather than looping.
- **`IdentitySyncService` matches on `idp_id` first, not email.** Changing an email
  address at the IDP forked a second local account and orphaned everything attached to
  the first. Email is still used as a fallback so accounts predating `idp_id` are
  adopted rather than duplicated. Where an install already holds forked rows, the most
  recently updated one wins — that is the account the person has actually been signing
  into, and moving them to a dormant duplicate would look like their data vanished.
- **`nevento:duplicate-identities`** finds accounts forked by the old matching (several
  rows sharing one `idp_id`). Reports by default; `--fix` clears `idp_id` on the stale
  rows so they stop competing for the identity. It deliberately does not delete rows or
  reassign related records — what those mean is specific to each app, and guessing
  would quietly corrupt data. **Run this once after upgrading.**
- **`RequireWorkspaceAccess` records the intended URL.** It used
  `redirect()->route()`, which stores nothing, so every deep link landed on the
  dashboard after signing in.
- **The post-login target is read from `services.nevento.redirect_after_login`.** It
  read `nevento.redirect_after_login`, a config file this package never publishes and
  no app ships, so it silently always returned `/admin`. The old key still works.

## [1.3.0] — 2026-07-23

Additive only — no changes to existing classes' public behaviour.

### Added
- `IdpClient` — generic HTTP client for calling the IDP's API with the session's
  SSO token, with automatic refresh-token retry on 401. Extracted from duplicated
  copies in `nevento-console` and `rento`/`myoffice`. Session key names and config
  namespace are constructor-configurable (defaults match this package's own
  `services.nevento.*` convention); host apps with different conventions can
  override the `IdpClient::class` binding in their own service provider.
- Registered as a container singleton by `NeventoServiceProvider` — resolvable via
  type-hint injection with no per-app setup needed for apps using the defaults.

## [1.2.0] — 2026-07-07

Additive only — no changes to the OAuth/Socialite surface. Existing consumers are
unaffected unless they opt into the new provisioning module.

### Added
- **Tenant-provisioning contract** so client apps expose a consistent internal
  provisioning API for the Nevento IDP instead of hand-rolling it:
  - `Contracts\TenantProvisioner` — interface the host app binds
    (`install`/`suspend`/`unsuspend`/`uninstall`/`status`). The package owns
    transport, auth and validation; the app supplies the real behaviour.
  - `Http\Middleware\VerifyProvisioningSecret` — fail-closed Bearer auth against
    the per-app shared secret (`config('nevento-provisioning.secret')`,
    env `APP_PROVISIONING_SECRET`).
  - `Http\Controllers\ProvisioningController` + `routes/provisioning.php` —
    stateless `/internal/health` and
    `/internal/tenants/{install,suspend,unsuspend,uninstall,status}` routes (no
    web/session/CSRF/tenancy). Registered automatically; opt out with
    `NEVENTO_PROVISIONING_ENABLED=false`.
  - `config/nevento-provisioning.php` — publishable via tag
    `nevento-provisioning-config`.

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
