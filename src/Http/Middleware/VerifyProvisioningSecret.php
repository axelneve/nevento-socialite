<?php

declare(strict_types=1);

namespace EventSolutions\NeventoSocialite\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Authenticates inbound provisioning calls from the IDP using the per-app shared
 * secret (Bearer). Fails closed: if no secret is configured on this side, every
 * request is rejected rather than allowed through.
 */
class VerifyProvisioningSecret
{
    public function handle(Request $request, Closure $next): Response
    {
        $expected = (string) config('nevento-provisioning.secret', '');
        $given = (string) $request->bearerToken();

        if ($expected === '' || $given === '' || ! hash_equals($expected, $given)) {
            abort(401, 'Invalid or missing provisioning secret.');
        }

        return $next($request);
    }
}
