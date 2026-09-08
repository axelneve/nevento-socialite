<?php

declare(strict_types=1);

namespace EventSolutions\NeventoSocialite\Http\Middleware;

use Closure;
use EventSolutions\NeventoSocialite\NeventoContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class RequireWorkspaceAccess
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        if (! Auth::check()) {
            // guest(), not route(): it records url.intended, so the callback can send
            // the user back to the page they actually asked for instead of the
            // dashboard. A plain redirect silently drops every deep link.
            return redirect()->guest(route('nevento.redirect'));
        }

        $workspace = session('nevento_workspace');

        if (! is_array($workspace) || empty($workspace['id'])) {
            Auth::logout();
            $request->session()->invalidate();

            // invalidate() first, then guest(): the intended URL has to be written to
            // the fresh session, or it goes out with the old one.
            return redirect()->guest(route('nevento.redirect'))
                ->withErrors(['auth' => 'Je sessie is verlopen. Log opnieuw in.']);
        }

        // Checks against the full roles array (and bypasses for superadmins) —
        // a strict superset of the old first-role-only check, so nobody who
        // previously passed can now be blocked.
        if (! empty($roles) && ! NeventoContext::hasAnyRole($roles)) {
            abort(403, 'Onvoldoende rechten voor deze actie.');
        }

        return $next($request);
    }
}
