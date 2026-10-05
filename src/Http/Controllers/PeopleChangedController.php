<?php

declare(strict_types=1);

namespace EventSolutions\NeventoSocialite\Http\Controllers;

use EventSolutions\NeventoSocialite\Contracts\SyncsPeople;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * The IDP tells this app that people in the register changed: workspace and uuids only,
 * no personal data. The app then fetches and stores them itself (SyncsPeople).
 *
 * Auth is the per-app provisioning secret (VerifyProvisioningSecret), like every other
 * internal call from the IDP.
 */
class PeopleChangedController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        if (! app()->bound(SyncsPeople::class)) {
            return response()->json(['message' => 'This app does not sync people yet.'], 404);
        }

        $data = $request->validate([
            'workspace_id' => ['required', 'integer', 'min:1'],
            'uuids' => ['sometimes', 'array', 'max:500'],
            'uuids.*' => ['uuid'],
        ]);

        app(SyncsPeople::class)->sync((int) $data['workspace_id'], array_values($data['uuids'] ?? []));

        return response()->json(['status' => 'ok']);
    }
}
