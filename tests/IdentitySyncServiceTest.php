<?php

declare(strict_types=1);

namespace EventSolutions\NeventoSocialite\Tests;

use EventSolutions\NeventoSocialite\Exceptions\WorkspaceAccessDeniedException;
use EventSolutions\NeventoSocialite\IdentitySyncService;
use EventSolutions\NeventoSocialite\Tests\Fixtures\TestUser;
use Laravel\Socialite\Two\User as SocialiteUser;
use PHPUnit\Framework\Attributes\Test;

class IdentitySyncServiceTest extends TestCase
{
    #[Test]
    public function it_creates_a_local_user_from_the_idp_identity(): void
    {
        $user = $this->sync(idpId: '42', email: 'ada@klant.nl', name: 'Ada');

        $this->assertSame('ada@klant.nl', $user->email);
        $this->assertSame('42', (string) $user->idp_id);
        $this->assertSame('admin', $user->workspace_role);
        $this->assertSame(1, TestUser::query()->count());
    }

    #[Test]
    public function an_email_change_at_the_idp_updates_the_same_account(): void
    {
        $this->sync(idpId: '42', email: 'oud@klant.nl', name: 'Ada');
        $this->sync(idpId: '42', email: 'nieuw@klant.nl', name: 'Ada');

        // Keying on email forked a second account here and orphaned everything
        // attached to the first.
        $this->assertSame(1, TestUser::query()->count());
        $this->assertSame('nieuw@klant.nl', TestUser::query()->first()->email);
    }

    #[Test]
    public function an_account_predating_the_idp_id_is_adopted_not_duplicated(): void
    {
        TestUser::query()->create(['name' => 'Legacy', 'email' => 'legacy@klant.nl']);

        $user = $this->sync(idpId: '77', email: 'legacy@klant.nl', name: 'Legacy');

        $this->assertSame(1, TestUser::query()->count());
        $this->assertSame('77', (string) $user->idp_id);
    }

    #[Test]
    public function two_different_people_stay_two_accounts(): void
    {
        $this->sync(idpId: '1', email: 'een@klant.nl', name: 'Een');
        $this->sync(idpId: '2', email: 'twee@klant.nl', name: 'Twee');

        $this->assertSame(2, TestUser::query()->count());
    }

    #[Test]
    public function a_user_without_workspace_roles_is_refused(): void
    {
        $this->expectException(WorkspaceAccessDeniedException::class);

        $this->sync(idpId: '9', email: 'geen@klant.nl', name: 'Geen', roles: []);
    }

    #[Test]
    public function it_populates_the_nevento_session_context(): void
    {
        $this->sync(idpId: '42', email: 'ada@klant.nl', name: 'Ada');

        $this->assertSame(['id' => '42', 'name' => 'Ada', 'email' => 'ada@klant.nl'], session('nevento_user'));
        $this->assertSame(['admin'], session('nevento_roles'));
        $this->assertSame('admin', session('nevento_role'));
        $this->assertFalse(session('nevento_superadmin'));
    }

    /**
     * @param  list<string>  $roles
     */
    private function sync(string $idpId, string $email, string $name, array $roles = ['admin']): TestUser
    {
        $raw = [
            'id' => $idpId,
            'name' => $name,
            'email' => $email,
            'is_superadmin' => false,
            'workspace' => $roles === [] ? ['id' => 1, 'roles' => []] : ['id' => 1, 'name' => 'WS', 'roles' => $roles],
            'workspaces' => [],
        ];

        $oauthUser = (new SocialiteUser)->setRaw($raw)->map([
            'id' => $idpId,
            'name' => $name,
            'email' => $email,
        ]);

        /** @var TestUser $user */
        $user = app(IdentitySyncService::class)->sync($raw, $oauthUser);

        return $user;
    }
}
