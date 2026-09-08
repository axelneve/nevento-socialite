<?php

declare(strict_types=1);

namespace EventSolutions\NeventoSocialite\Tests;

use EventSolutions\NeventoSocialite\Tests\Fixtures\TestUser;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;

class BackChannelLogoutTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('nevento-provisioning.secret', 'shared-secret');
        config()->set('session.driver', 'database');
        config()->set('session.connection', null);

        Schema::create('sessions', function (Blueprint $table): void {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    #[Test]
    public function it_ends_the_users_sessions_here(): void
    {
        $user = TestUser::query()->create(['name' => 'Ada', 'email' => 'ada@klant.nl', 'idp_id' => '42']);
        $this->seedSession('keep-other', null);
        $this->seedSession('end-me-1', $user->id);
        $this->seedSession('end-me-2', $user->id);

        $this->withToken('shared-secret')
            ->postJson('/internal/sessions/logout', ['sub' => '42'])
            ->assertOk()
            ->assertJsonPath('sessions_ended', 2);

        $this->assertSame(0, DB::table('sessions')->where('user_id', $user->id)->count());
        // Someone else's session must survive.
        $this->assertSame(1, DB::table('sessions')->where('id', 'keep-other')->count());
    }

    #[Test]
    public function it_requires_the_provisioning_secret(): void
    {
        TestUser::query()->create(['name' => 'Ada', 'email' => 'ada@klant.nl', 'idp_id' => '42']);

        $this->postJson('/internal/sessions/logout', ['sub' => '42'])->assertUnauthorized();

        $this->withToken('wrong-secret')
            ->postJson('/internal/sessions/logout', ['sub' => '42'])
            ->assertUnauthorized();
    }

    #[Test]
    public function an_unknown_subject_is_a_success_not_an_error(): void
    {
        // Saying "no such user" would confirm to the caller which users exist here.
        $this->withToken('shared-secret')
            ->postJson('/internal/sessions/logout', ['sub' => 'nobody'])
            ->assertOk()
            ->assertJsonPath('sessions_ended', 0);
    }

    #[Test]
    public function a_missing_subject_is_rejected(): void
    {
        $this->withToken('shared-secret')
            ->postJson('/internal/sessions/logout', [])
            ->assertStatus(422);
    }

    private function seedSession(string $id, ?int $userId): void
    {
        DB::table('sessions')->insert([
            'id' => $id,
            'user_id' => $userId,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'test',
            'payload' => '',
            'last_activity' => now()->timestamp,
        ]);
    }
}
