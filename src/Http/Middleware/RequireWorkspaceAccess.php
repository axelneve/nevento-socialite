<?php

declare(strict_types=1);

namespace EventSolutions\NeventoSocialite\Http\Middleware;

use Closure;
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

        if (! empty($roles)) {
            $userRole = session('nevento_role', '');

            if (! in_array($userRole, $roles, true)) {
                abort(403, 'Onvoldoende rechten voor deze actie.');
            }
        }

        return $next($request);
    }
}
