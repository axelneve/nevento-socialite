<?php

declare(strict_types=1);

namespace EventSolutions\NeventoSocialite\Http\Controllers;

use EventSolutions\NeventoSocialite\Exceptions\WorkspaceAccessDeniedException;
use EventSolutions\NeventoSocialite\IdentitySyncService;
use GuzzleHttp\Exception\ClientException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\AbstractProvider;
use Laravel\Socialite\Two\InvalidStateException;

class OAuthController extends Controller
{
    /** Marks that a state mismatch has already been retried once this session. */
    private const RESTARTED_KEY = 'nevento_state_restarted';

    public function __construct(private readonly IdentitySyncService $sync) {}

    public function redirect(): RedirectResponse
    {
        return $this->driver()->redirect();
    }

    public function callback(Request $request): RedirectResponse|Response
    {
        if ($request->filled('error')) {
            return $this->errorView(
                (string) $request->query('error'),
                (string) $request->query('error_description', 'Er ging iets mis tijdens het inloggen.')
            );
        }

        $driver = $this->driver();

        try {
            $remoteUser = $driver->user();
        } catch (InvalidStateException) {
            // Previously this retried with ->stateless(), which turns a failed CSRF
            // state check into "try again without the check" — exactly what state is
            // there to prevent. A mismatch nearly always means the session did not
            // survive the round trip, so restart the flow instead of downgrading it.
            return $this->restartAfterInvalidState($request);
        } catch (ClientException $e) {
            $body   = (string) $e->getResponse()?->getBody();
            $parsed = json_decode($body, true);
            $msg    = is_array($parsed) ? ($parsed['error_description'] ?? 'OAuth fout') : 'OAuth fout';

            return $this->errorView('oauth_error', $msg);
        }

        try {
            $user = $this->sync->sync($remoteUser->getRaw(), $remoteUser);
        } catch (WorkspaceAccessDeniedException) {
            return $this->errorView(
                'access_denied',
                'Je hebt geen toegang tot dit portaal. Neem contact op met de beheerder.'
            );
        }

        Auth::login($user, remember: true);

        $request->session()->forget(self::RESTARTED_KEY);

        return redirect()->intended($this->redirectAfterLogin());
    }

    /**
     * A state mismatch is recoverable — the user simply needs a fresh flow — but only
     * once. Retrying blindly would bounce a genuinely broken session forever.
     */
    private function restartAfterInvalidState(Request $request): RedirectResponse|Response
    {
        if ($request->session()->pull(self::RESTARTED_KEY, false)) {
            return $this->errorView(
                'invalid_state',
                'Je inlogsessie is verlopen. Controleer of cookies zijn toegestaan en probeer opnieuw.'
            );
        }

        $request->session()->put(self::RESTARTED_KEY, true);

        return redirect()->route('nevento.redirect');
    }

    /**
     * `config('nevento.redirect_after_login')` read a config file this package never
     * publishes and no app ships, so it silently always returned '/admin'. The real
     * key lives with the rest of the package config; the old one is still honoured.
     */
    private function redirectAfterLogin(): string
    {
        return (string) config(
            'services.nevento.redirect_after_login',
            config('nevento.redirect_after_login', '/admin')
        );
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        $idpLogout = rtrim((string) config('services.nevento.logout_url', ''), '/');

        if ($idpLogout !== '') {
            return redirect()->away($idpLogout.'?redirect='.urlencode(route('nevento.redirect')));
        }

        return redirect()->route('nevento.redirect');
    }

    private function driver(): AbstractProvider
    {
        /** @var AbstractProvider $driver */
        $driver = Socialite::driver('nevento')
            ->redirectUrl(config('services.nevento.redirect', url('/auth/callback')))
            ->setScopes(config('services.nevento.scopes', ['openid', 'profile', 'email', 'workspaces:read']));

        if (config('services.nevento.use_pkce', true)) {
            $driver->enablePKCE();
        }

        if (config('services.nevento.stateless', false)) {
            $driver = $driver->stateless();
        }

        return $driver;
    }

    private function errorView(string $code, string $message): Response
    {
        if (view()->exists('nevento::auth.error')) {
            return response()->view('nevento::auth.error', compact('code', 'message'), 401);
        }

        return response("<h1>Inloggen mislukt</h1><p>{$message}</p><p><a href='".route('nevento.redirect')."'>Opnieuw proberen</a></p>", 401);
    }
}
