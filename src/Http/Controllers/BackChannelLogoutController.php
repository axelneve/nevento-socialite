<?php

declare(strict_types=1);

namespace EventSolutions\NeventoSocialite\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Ends this app's sessions for a user who has signed out of the IDP.
 *
 * Without it, "log out" at the IDP left every app session running until it expired
 * on its own — which is not logging out, it just looks like it.
 *
 * Auth is the per-app provisioning secret (VerifyProvisioningSecret), the same
 * server-to-server channel the IDP already uses. The signed logout_token is carried
 * too so an app that wants to verify it against the IDP's JWKS can.
 */
class BackChannelLogoutController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $data = $request->validate([
            'sub' => ['required', 'string', 'max:64'],
            'logout_token' => ['nullable', 'string'],
        ]);

        $userId = $this->resolveLocalUserId((string) $data['sub']);

        if ($userId === null) {
            // Nothing to do is a success: the user may simply never have signed in here.
            return response()->json(['status' => 'ok', 'sessions_ended' => 0]);
        }

        $ended = $this->endSessions($userId);

        Log::info('Back-channel logout processed.', [
            'idp_user_id' => $data['sub'],
            'sessions_ended' => $ended,
        ]);

        return response()->json(['status' => 'ok', 'sessions_ended' => $ended]);
    }

    /**
     * Apps disagree on the column name: the package's own IdentitySyncService writes
     * `idp_id`, while the hand-rolled ones (rento, myOffice, kasso) use `idp_user_id`.
     */
    private function resolveLocalUserId(string $idpUserId): int|string|null
    {
        $model = config('auth.providers.users.model', \App\Models\User::class);

        /** @var \Illuminate\Database\Eloquent\Model $instance */
        $instance = new $model;
        $table = $instance->getTable();

        foreach (['idp_user_id', 'idp_id'] as $column) {
            if (! Schema::hasColumn($table, $column)) {
                continue;
            }

            $id = DB::table($table)->where($column, $idpUserId)->value($instance->getKeyName());

            if ($id !== null) {
                return $id;
            }
        }

        return null;
    }

    /**
     * Only the database session driver keeps a queryable index of sessions; with file
     * or cookie sessions there is nothing to delete from this side.
     */
    private function endSessions(int|string $userId): int
    {
        if (config('session.driver') !== 'database') {
            Log::warning('Back-channel logout received but session driver is not "database"; sessions cannot be ended remotely.');

            return 0;
        }

        return DB::connection(config('session.connection'))
            ->table(config('session.table', 'sessions'))
            ->where('user_id', $userId)
            ->delete();
    }
}
