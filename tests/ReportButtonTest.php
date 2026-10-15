<?php

declare(strict_types=1);

namespace EventSolutions\NeventoSocialite\Tests;

use EventSolutions\NeventoSocialite\System\SystemClient;
use EventSolutions\NeventoSocialite\Tests\Fixtures\TestUser;
use Illuminate\Http\Client\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;

class ReportButtonTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        SystemClient::resetBackoff();
        config(['nevento-system.url' => 'https://system.test', 'nevento-system.token' => 'sys_secret']);
        Route::get('/login', fn () => 'login')->name('login')->middleware('web');
    }

    private function signIn(): static
    {
        $user = TestUser::create(['name' => 'Piet', 'email' => 'piet@example.nl', 'idp_id' => '42']);

        return $this->actingAs($user)->withSession([
            'nevento_user' => ['id' => 42, 'name' => 'Piet Idp', 'email' => 'piet@idp.nl'],
            'nevento_workspace' => ['id' => 7, 'name' => 'Acme'],
            'nevento_role' => 'admin',
            'nevento_roles' => ['admin', 'office'],
        ]);
    }

    /** @return array<string, mixed> */
    private function form(array $extra = []): array
    {
        return $extra + ['type' => 'bug', 'summary' => 'Knop werkt niet', 'description' => 'Klikken doet niets', 'severity' => 'high', 'context' => ['current_url' => 'https://app.test/x', 'route_name' => 'x.index', 'viewport' => '']];
    }

    public function test_a_report_is_forwarded_with_who_where_and_the_screenshot(): void
    {
        Http::fake(['system.test/*' => Http::response(['id' => 1], 201)]);

        $this->signIn()->from('/x')->post(route('nevento.report'), $this->form(['screenshot' => UploadedFile::fake()->image('s.png')]))
            ->assertRedirect('/x')->assertSessionHas('nevento_report.ok', true);

        Http::assertSent(function (Request $request) {
            $data = $request->data();

            return $request->url() === 'https://system.test/api/v1/reports'
                && $request->hasHeader('Authorization', 'Bearer sys_secret')
                && $data['type'] === 'bug' && $data['summary'] === 'Knop werkt niet' && $data['severity'] === 'high'
                && $data['reporter_name'] === 'Piet Idp' && $data['reporter_email'] === 'piet@idp.nl' && $data['reporter_idp_id'] === '42'
                && $data['reporter_role'] === 'admin' && $data['workspace'] === '7'
                && $data['page_url'] === 'https://app.test/x' && $data['route_name'] === 'x.index'
                && $data['context']['client'] === ['current_url' => 'https://app.test/x', 'route_name' => 'x.index']
                && $data['context']['server']['workspace_name'] === 'Acme' && $data['context']['server']['roles'] === ['admin', 'office']
                && str_starts_with($data['screenshot'], 'data:image/png;base64,') && strlen($data['source_ref']) === 36;
        });
    }

    public function test_the_user_is_told_when_the_system_cannot_be_reached(): void
    {
        Http::fake(['system.test/*' => Http::response('', 500)]);

        $this->signIn()->from('/x')->post(route('nevento.report'), $this->form())->assertSessionHas('nevento_report.ok', false);
        $this->postJson(route('nevento.report'), $this->form())->assertStatus(502)->assertJson(['ok' => false]);
    }

    public function test_it_is_validated_and_only_for_logged_in_users_and_only_when_enabled(): void
    {
        Http::fake();

        $this->post(route('nevento.report'), $this->form())->assertRedirect('/login');
        $this->signIn()->post(route('nevento.report'), $this->form(['type' => 'nonsense']))->assertSessionHasErrors('type');
        $this->post(route('nevento.report'), $this->form(['summary' => '']))->assertSessionHasErrors('summary');
        Http::assertNothingSent();

        config(['nevento-system.token' => null]);
        $this->post(route('nevento.report'), $this->form())->assertNotFound();
    }

    public function test_my_reports_are_read_for_one_reporter_and_a_failure_gives_null(): void
    {
        Http::fake(['system.test/api/v1/reports*' => Http::sequence()
            ->push(['data' => [['id' => 3, 'type' => 'bug', 'summary' => 'Knop', 'status' => 'open', 'created_at' => '2026-10-15T08:00:00Z', 'resolved_at' => null]]])
            ->push('', 500)]);

        $mine = app(SystemClient::class)->myReports('piet@example.nl');
        $this->assertSame('Knop', $mine[0]['summary']);
        Http::assertSent(fn (Request $request) => str_contains($request->url(), 'reporter=piet%40example.nl') && $request->hasHeader('Authorization', 'Bearer sys_secret'));

        SystemClient::resetBackoff();
        $this->assertNull(app(SystemClient::class)->myReports('piet@example.nl'));
        $this->assertNull(app(SystemClient::class)->myReports('piet@example.nl')); // backed off: no second call

        config(['nevento-system.token' => null]);
        SystemClient::resetBackoff();
        $this->assertNull(app(SystemClient::class)->myReports('x'));
    }

    public function test_the_button_renders_for_a_logged_in_user_with_a_token_only(): void
    {
        $this->assertSame('', trim(Blade::render('<x-nevento::report-button />')));

        $this->signIn();
        $this->assertStringContainsString('class="nvr-open" data-nevento-report>Feedback<', Blade::render('<x-nevento::report-button label="Feedback" />'));
        $this->assertStringNotContainsString('class="nvr-open"', Blade::render('<x-nevento::report-button :floating="false" />'));
        $this->assertStringContainsString('data-nvr-dialog', Blade::render('<x-nevento::report-button :floating="false" />'));

        config(['nevento-system.token' => null]);
        $this->assertSame('', trim(Blade::render('<x-nevento::report-button />')));
    }
}
