<?php

declare(strict_types=1);

namespace EventSolutions\NeventoSocialite\Tests;

use EventSolutions\NeventoSocialite\Tests\Fixtures\TestUser;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\Test;

class OAuthCallbackTest extends TestCase
{
    #[Test]
    public function a_state_mismatch_restarts_the_flow_instead_of_dropping_the_state_check(): void
    {
        // No `state` in the session, so Socialite raises InvalidStateException. This
        // used to retry with ->stateless(), which accepts the code with no CSRF state
        // validation at all — the opposite of what the failure is telling us.
        $response = $this->get('/auth/callback?code=some-code&state=attacker-supplied');

        $response->assertRedirect(route('nevento.redirect'));
        $this->assertGuest();
    }

    #[Test]
    public function a_second_state_mismatch_stops_rather_than_looping(): void
    {
        $this->get('/auth/callback?code=a&state=b')->assertRedirect(route('nevento.redirect'));

        // A genuinely broken session (cookies blocked, say) must not bounce forever.
        $this->get('/auth/callback?code=a&state=b')
            ->assertStatus(401)
            ->assertSee('Inloggen mislukt', escape: false);
    }

    #[Test]
    public function an_error_from_the_idp_is_shown_not_swallowed(): void
    {
        $this->get('/auth/callback?error=access_denied&error_description=Geweigerd')
            ->assertStatus(401)
            ->assertSee('Geweigerd', escape: false);
    }

    #[Test]
    public function the_post_login_target_comes_from_the_services_config(): void
    {
        // config('nevento.redirect_after_login') read a file this package never
        // publishes and no app ships, so it always silently returned '/admin'.
        config()->set('services.nevento.redirect_after_login', '/dashboard');

        $controller = new \ReflectionClass(\EventSolutions\NeventoSocialite\Http\Controllers\OAuthController::class);
        $method = $controller->getMethod('redirectAfterLogin');
        $method->setAccessible(true);

        $instance = app(\EventSolutions\NeventoSocialite\Http\Controllers\OAuthController::class);

        $this->assertSame('/dashboard', $method->invoke($instance));
    }

    #[Test]
    public function the_legacy_config_key_is_still_honoured(): void
    {
        config()->set('nevento.redirect_after_login', '/legacy');

        $controller = new \ReflectionClass(\EventSolutions\NeventoSocialite\Http\Controllers\OAuthController::class);
        $method = $controller->getMethod('redirectAfterLogin');
        $method->setAccessible(true);

        $this->assertSame('/legacy', $method->invoke(app(\EventSolutions\NeventoSocialite\Http\Controllers\OAuthController::class)));
    }

    #[Test]
    public function the_workspace_middleware_records_where_the_user_was_going(): void
    {
        Route::middleware(['web', \EventSolutions\NeventoSocialite\Http\Middleware\RequireWorkspaceAccess::class])
            ->get('/deep/link', fn () => 'ok');

        $this->get('/deep/link')->assertRedirect(route('nevento.redirect'));

        // A plain redirect()->route() dropped this, so every deep link landed on the
        // dashboard after signing in.
        $this->assertSame(url('/deep/link'), session('url.intended'));
    }

    #[Test]
    public function a_signed_in_user_without_a_workspace_is_sent_back_to_sign_in(): void
    {
        Route::middleware(['web', \EventSolutions\NeventoSocialite\Http\Middleware\RequireWorkspaceAccess::class])
            ->get('/needs-workspace', fn () => 'ok');

        $user = TestUser::query()->create(['name' => 'Ada', 'email' => 'ada@klant.nl', 'idp_id' => '1']);

        $this->actingAs($user)->get('/needs-workspace')->assertRedirect(route('nevento.redirect'));

        $this->assertGuest();
    }
}
