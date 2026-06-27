<?php

declare(strict_types=1);

namespace EventSolutions\NeventoSocialite;

use EventSolutions\NeventoSocialite\Exceptions\WorkspaceAccessDeniedException;
use Illuminate\Contracts\Auth\Authenticatable;
use Laravel\Socialite\Contracts\User as SocialiteUser;

class IdentitySyncService
{
    /**
     * Sync IDP user data to the local User model and session.
     *
     * The IDP is the authority on which workspace this token belongs to —
     * the OAuth client's workspace_id enforces it server-side.
     *
     * @throws WorkspaceAccessDeniedException
     */
    public function sync(array $rawUser, SocialiteUser $oauthUser): Authenticatable
    {
        // The IDP returns the single workspace the OAuth client is scoped to.
        $workspace = $rawUser['workspace'] ?? null;

        if (! is_array($workspace) || empty($workspace['roles'])) {
            throw new WorkspaceAccessDeniedException();
        }

        $role  = (string) ($workspace['roles'][0] ?? 'office');
        $email = (string) ($oauthUser->getEmail() ?: data_get($rawUser, 'email', ''));
        $name  = (string) ($oauthUser->getName() ?: data_get($rawUser, 'name', ''));
        $idpId = $oauthUser->getId() ?: data_get($rawUser, 'id');

        $modelClass = config('auth.providers.users.model', \App\Models\User::class);

        /** @var \Illuminate\Database\Eloquent\Model $user */
        $user = $modelClass::updateOrCreate(
            ['email' => $email],
            [
                'name'           => $name,
                'idp_id'         => $idpId,
                'workspace_role' => $role,
            ]
        );

        session([
            'nevento_user'             => ['id' => $idpId, 'name' => $name, 'email' => $email],
            'nevento_workspace'        => $workspace,
            'nevento_role'             => $role,
            'nevento_token_expires_at' => is_numeric($oauthUser->expiresIn ?? null)
                ? now()->addSeconds((int) $oauthUser->expiresIn)->timestamp
                : null,
        ]);

        return $user;
    }
}
