<?php

declare(strict_types=1);

namespace EventSolutions\NeventoSocialite\Tests;

use EventSolutions\NeventoSocialite\Contracts\SyncsPeople;
use PHPUnit\Framework\Attributes\Test;

class PeopleChangedTest extends TestCase
{
    private const UUID_A = '6f1f7b0e-8c1a-4a3e-9d57-2b8d3d7f0a11';

    private const UUID_B = '0d4c1e52-52f5-4a4b-8d0e-5c3f0a9b7e22';

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('nevento-provisioning.secret', 'shared-secret');
    }

    private function bindRecorder(): object
    {
        $recorder = new class implements SyncsPeople
        {
            /** @var list<array{int, list<string>}> */
            public array $calls = [];

            public function sync(int $workspaceId, array $uuids): void
            {
                $this->calls[] = [$workspaceId, $uuids];
            }
        };
        $this->app->instance(SyncsPeople::class, $recorder);

        return $recorder;
    }

    #[Test]
    public function it_hands_the_workspace_and_uuids_to_the_app(): void
    {
        $recorder = $this->bindRecorder();

        $this->withToken('shared-secret')
            ->postJson('/internal/people/changed', ['workspace_id' => 7, 'uuids' => [self::UUID_A, self::UUID_B]])
            ->assertOk()
            ->assertJsonPath('status', 'ok');

        $this->assertSame([[7, [self::UUID_A, self::UUID_B]]], $recorder->calls);
    }

    #[Test]
    public function without_uuids_it_asks_for_a_full_refresh(): void
    {
        $recorder = $this->bindRecorder();

        $this->withToken('shared-secret')->postJson('/internal/people/changed', ['workspace_id' => 7])->assertOk();

        $this->assertSame([[7, []]], $recorder->calls);
    }

    #[Test]
    public function it_answers_404_until_the_app_binds_an_implementation(): void
    {
        $this->withToken('shared-secret')
            ->postJson('/internal/people/changed', ['workspace_id' => 7])
            ->assertNotFound();
    }

    #[Test]
    public function it_requires_the_provisioning_secret(): void
    {
        $recorder = $this->bindRecorder();

        $this->postJson('/internal/people/changed', ['workspace_id' => 7])->assertUnauthorized();
        $this->withToken('wrong')->postJson('/internal/people/changed', ['workspace_id' => 7])->assertUnauthorized();

        $this->assertSame([], $recorder->calls);
    }

    #[Test]
    public function it_rejects_a_malformed_payload_without_calling_the_app(): void
    {
        $recorder = $this->bindRecorder();

        $this->withToken('shared-secret')->postJson('/internal/people/changed', [])->assertUnprocessable();
        $this->withToken('shared-secret')->postJson('/internal/people/changed', ['workspace_id' => 7, 'uuids' => ['not-a-uuid']])->assertUnprocessable();
        $this->withToken('shared-secret')->postJson('/internal/people/changed', ['workspace_id' => 'x'])->assertUnprocessable();

        $this->assertSame([], $recorder->calls);
    }
}
