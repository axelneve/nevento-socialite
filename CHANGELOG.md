# Changelog

All notable changes to this package will be documented here.

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
