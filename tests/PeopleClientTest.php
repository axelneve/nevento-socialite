<?php

declare(strict_types=1);

namespace EventSolutions\NeventoSocialite\Tests;

use EventSolutions\NeventoSocialite\PeopleClient;
use EventSolutions\NeventoSocialite\PeopleClientException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;

class PeopleClientTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
    }

    private function client(): PeopleClient
    {
        return new PeopleClient('https://idp.test');
    }

    private function token(): array
    {
        return ['https://idp.test/oauth/token' => Http::response(['access_token' => 'app-token', 'expires_in' => 3600])];
    }

    #[Test]
    public function it_authenticates_as_the_app_and_caches_the_token(): void
    {
        Http::fake($this->token() + ['https://idp.test/api/*' => Http::response(['data' => [], 'meta' => ['last_page' => 1]])]);

        $this->client()->list(7);
        $this->client()->list(7);

        Http::assertSent(fn (Request $request): bool => str_ends_with($request->url(), '/oauth/token')
            && $request['grant_type'] === 'client_credentials'
            && $request['client_id'] === 'test-client'
            && $request['scope'] === 'people:read people:write');
        $tokenCalls = Http::recorded(fn (Request $request): bool => str_ends_with($request->url(), '/oauth/token'));
        $this->assertCount(1, $tokenCalls);
        Http::assertSent(fn (Request $request): bool => str_contains($request->url(), '/api/v1/workspaces/7/people')
            && $request->hasHeader('Authorization', 'Bearer app-token'));
    }

    #[Test]
    public function list_follows_every_page_and_passes_updated_since(): void
    {
        Http::fake($this->token() + [
            'https://idp.test/api/v1/workspaces/7/people*' => Http::sequence()
                ->push(['data' => [['uuid' => 'a'], ['uuid' => 'b']], 'meta' => ['last_page' => 2]])
                ->push(['data' => [['uuid' => 'c']], 'meta' => ['last_page' => 2]]),
        ]);

        $people = $this->client()->list(7, new \DateTimeImmutable('2026-10-01T10:00:00+00:00'));

        $this->assertSame(['a', 'b', 'c'], array_column($people, 'uuid'));
        Http::assertSent(fn (Request $request): bool => str_contains($request->url(), 'updated_since=2026-10-01T10%3A00%3A00%2B00%3A00')
            && str_contains($request->url(), 'page=2'));
    }

    #[Test]
    public function create_and_update_send_the_data_and_return_the_person(): void
    {
        Http::fake($this->token() + [
            'https://idp.test/api/v1/workspaces/7/people' => Http::response(['data' => ['uuid' => 'new', 'email' => 'sam@x.test']], 201),
            'https://idp.test/api/v1/workspaces/7/people/new' => Http::response(['data' => ['uuid' => 'new', 'active' => false]]),
        ]);

        $created = $this->client()->create(7, ['name' => 'Sam', 'email' => 'sam@x.test']);
        $updated = $this->client()->update(7, 'new', ['active' => false]);

        $this->assertSame('new', $created['uuid']);
        $this->assertFalse($updated['active']);
        Http::assertSent(fn (Request $request): bool => $request->method() === 'PATCH' && $request['active'] === false);
    }

    #[Test]
    public function a_refusal_throws_with_the_status_and_the_field_errors(): void
    {
        Http::fake($this->token() + [
            'https://idp.test/api/*' => Http::response(['message' => 'The email has already been taken.', 'errors' => ['email' => ['The email has already been taken.']]], 422),
        ]);

        try {
            $this->client()->create(7, ['name' => 'Sam', 'email' => 'sam@x.test']);
            $this->fail('Expected a PeopleClientException.');
        } catch (PeopleClientException $e) {
            $this->assertSame(422, $e->status);
            $this->assertSame(['email' => ['The email has already been taken.']], $e->errors);
        }
    }

    #[Test]
    public function a_401_refreshes_the_token_once_and_retries(): void
    {
        Http::fake([
            'https://idp.test/oauth/token' => Http::sequence()
                ->push(['access_token' => 'old', 'expires_in' => 3600])
                ->push(['access_token' => 'fresh', 'expires_in' => 3600]),
            'https://idp.test/api/*' => Http::sequence()
                ->push([], 401)
                ->push(['data' => [], 'meta' => ['last_page' => 1]]),
        ]);

        $this->assertSame([], $this->client()->list(7));
        Http::assertSent(fn (Request $request): bool => $request->hasHeader('Authorization', 'Bearer fresh'));
    }

    #[Test]
    public function an_idp_that_gives_no_token_is_reported(): void
    {
        Http::fake(['https://idp.test/oauth/token' => Http::response(['error' => 'invalid_client', 'error_description' => 'Client authentication failed'], 401)]);

        $this->expectException(PeopleClientException::class);
        $this->expectExceptionMessage('Client authentication failed');
        $this->client()->list(7);
    }
}
