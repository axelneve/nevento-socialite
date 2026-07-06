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
            return redirect()->route('nevento.redirect');
        }

        $workspace = session('nevento_workspace');

        if (! is_array($workspace) || empty($workspace['id'])) {
            Auth::logout();
            $request->session()->invalidate();

            return redirect()->route('nevento.redirect')
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
