# eventsolutions/nevento-socialite

Private Laravel package that wires client website backends (e.g. VOER, Rento) into the Nevento IDP via OAuth2 authorization code flow.

Each site gets its own OAuth client in the IDP, scoped to a specific workspace and app. The IDP enforces which workspace the token belongs to server-side — no per-site slug discriminator is needed in the client application.

---

## Requirements

| Dependency | Version |
|---|---|
| PHP | ^8.3 |
| Laravel | ^12.0 \| ^13.0 |
| laravel/socialite | ^5.0 |
| Nevento IDP | with `client-sites` app and `workspace_id`/`app_id` on `oauth_clients` |

---

## Installation

### Local development (path repository)

Add to the consuming app's `composer.json`:

```json
"repositories": [
    {
        "type": "path",
        "url": "../nevento-socialite",
        "options": { "symlink": true }
    }
],
"require": {
    "eventsolutions/nevento-socialite": "*"
}
```

Then run:

```bash
composer update eventsolutions/nevento-socialite
```

### Production (private Packagist / Satis)

Add the private registry to `composer.json` and require the package normally:

```bash
composer require eventsolutions/nevento-socialite
```

The `NeventoServiceProvider` is auto-discovered via `extra.laravel.providers` — no manual registration needed.

---

## IDP setup (one-time, per new client site)

Do this in the **Nevento Console → SuperAdmin** before configuring the site backend.

### 1. Create or identify the workspace

The workspace slug becomes the logical identity of the site (e.g. `voer-catering`). Create one via **SuperAdmin → Workspaces** if it doesn't exist.

### 2. Assign the `client-sites` license

**SuperAdmin → Workspace Apps → Add**

| Field | Value |
|---|---|
| Workspace | The site's workspace |
| App | `client-sites` |
| License type | `Gratis` |
| Status | `active` |
| Admin seats | 1+ |

### 3. Add user(s) to the `client-sites` app

**SuperAdmin → Workspaces → [workspace] → Provisioning → Users → Add to app**

Pick `client-sites` and assign the role (`admin` or `office`).

### 4. Create a per-site OAuth client

**SuperAdmin → OAuth Clients → New**

| Field | Value |
|---|---|
| Name | e.g. `VOER. Catering (productie)` |
| Workspace | The site's workspace |
| App | `client-sites` |
| Grant types | `authorization_code`, `refresh_token` |
| Redirect URI | `https://yoursite.nl/auth/callback` |

**Copy the generated `client_id` and `secret` — the secret is shown only once.**

---

## Site backend setup

### 1. Add to `config/services.php`

```php
'nevento' => [
    'client_id'     => env('NEVENTO_CLIENT_ID'),
    'client_secret' => env('NEVENTO_CLIENT_SECRET'),
    'redirect'      => env('NEVENTO_REDIRECT_URL', '/auth/callback'),
    'host'          => env('NEVENTO_HOST', 'https://idp.nevento.nl'),
    'use_pkce'      => env('NEVENTO_USE_PKCE', true),
    'stateless'     => env('NEVENTO_STATELESS', false),
    'scopes'        => ['openid', 'profile', 'email', 'workspaces:read'],
    'load_routes'   => true,
],
```

### 2. Add to `.env`

```env
NEVENTO_HOST=https://idp.nevento.nl
NEVENTO_CLIENT_ID=<client_id from console>
NEVENTO_CLIENT_SECRET=<secret from console>
NEVENTO_REDIRECT_URL=https://yoursite.nl/auth/callback
NEVENTO_USE_PKCE=false
NEVENTO_STATELESS=false
```

> **Local dev:** Set `NEVENTO_HOST=http://nevento-idp.test` and `NEVENTO_REDIRECT_URL=http://yoursite.test/auth/callback`.

### 3. Add columns to the `users` table

```php
// In a migration:
$table->string('idp_id')->nullable()->unique()->after('id');
$table->string('workspace_role')->nullable()->after('email');
```

Add both to `$fillable` on the `User` model.

### 4. Register the middleware alias

In `bootstrap/app.php`:

```php
->withMiddleware(function (Middleware $middleware): void {
    $middleware->alias([
        'nevento.workspace' => \EventSolutions\NeventoSocialite\Http\Middleware\RequireWorkspaceAccess::class,
    ]);
})
```

### 5. Wire the login redirect

Add a named `login` route that redirects unauthenticated users to the IDP:

```php
// routes/web.php
Route::get('/login', fn () => redirect()->route('nevento.redirect'))->name('login');
```

For **Filament**, disable the built-in login page so unauthenticated `/admin` requests flow through the `login` route:

```php
// AdminPanelProvider
->login(null)
```

---

## IDP OAuth endpoints

The package connects to the following endpoints on `NEVENTO_HOST`:

| Endpoint | URL |
|---|---|
| Authorization | `{NEVENTO_HOST}/oauth/authorize` |
| Token exchange | `{NEVENTO_HOST}/oauth/token` |
| User info | `{NEVENTO_HOST}/api/user` |

Production host: `https://idp.nevento.nl`

---

## Routes registered by the package

When `services.nevento.load_routes` is `true` (the default), three routes are registered automatically:

| Method | URI | Name |
|---|---|---|
| GET | `/auth/redirect` | `nevento.redirect` |
| GET | `/auth/callback` | `nevento.callback` |
| POST | `/auth/logout` | `nevento.logout` |

To disable auto-registration (e.g. to register routes under a custom prefix yourself):

```php
'nevento' => [
    // ...
    'load_routes' => false,
],
```

---

## Protecting routes

Use the `nevento.workspace` middleware alias:

```php
// Any authenticated workspace user
Route::middleware('nevento.workspace')->group(function () {
    // ...
});

// Restrict to specific roles
Route::middleware('nevento.workspace:admin')->group(function () {
    // admin only
});

Route::middleware('nevento.workspace:admin,office')->group(function () {
    // admin or office
});
```

---

## Logout

Post to the `nevento.logout` route. If `services.nevento.logout_url` is set, the user is redirected to the IDP's SSO logout endpoint afterward:

```php
'nevento' => [
    // ...
    'logout_url' => env('NEVENTO_LOGOUT_URL', ''),
],
```

```blade
<form method="POST" action="{{ route('nevento.logout') }}">
    @csrf
    <button type="submit">Uitloggen</button>
</form>
```

---

## Session keys

After a successful login the following session keys are written:

| Key | Contents |
|---|---|
| `nevento_user` | `['id', 'name', 'email']` from the IDP |
| `nevento_workspace` | `['id', 'name', 'slug', 'roles', 'is_admin']` |
| `nevento_role` | First role string, e.g. `'admin'` |
| `nevento_token_expires_at` | Unix timestamp of token expiry, or `null` |

---

## Custom error view

If a Blade view `nevento::auth.error` exists in your application, the package renders it instead of inline HTML on auth failures. It receives two variables:

| Variable | Example |
|---|---|
| `$code` | `'access_denied'`, `'oauth_error'`, `'invalid_state'` |
| `$message` | Human-readable Dutch error message |

To publish and customise the fallback view, create `resources/views/vendor/nevento/auth/error.blade.php` and register the view namespace in your `AppServiceProvider`:

```php
View::addNamespace('nevento', resource_path('views/vendor/nevento'));
```

---

## How it works

```
Browser → /auth/redirect
    → Nevento IDP /oauth/authorize (authorization code + optional PKCE)
    → Browser /auth/callback?code=...
    → IDP /oauth/token  (exchange code for access token)
    → IDP /api/user     (Bearer token — IDP reads client's workspace_id + app_id
                          from the token record and returns only that workspace)
    → IdentitySyncService::sync()
        → upserts User (email key), sets idp_id + workspace_role
        → writes nevento_* session keys
    → Auth::login($user)
    → redirect()->intended('/admin')
```

The IDP enforces workspace ownership at the OAuth client level — each client carries `workspace_id` and `app_id`. The `/api/user` endpoint filters the returned workspace list to exactly those values, so the site backend never needs to name its own workspace.

---

## Adding a new client site

1. Create workspace + assign `client-sites` license in the console
2. Add user(s) to `client-sites` in that workspace
3. Create a new OAuth client scoped to that workspace + `client-sites` app
4. Install this package in the new site (`composer require eventsolutions/nevento-socialite`)
5. Add the `services.nevento` config block and env vars (only `NEVENTO_CLIENT_ID`, `NEVENTO_CLIENT_SECRET`, `NEVENTO_REDIRECT_URL` differ per site)
6. Run the `users` table migration
7. Register the middleware alias and the `login` redirect route
