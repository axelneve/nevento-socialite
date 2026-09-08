<?php

declare(strict_types=1);

namespace EventSolutions\NeventoSocialite\Tests;

use EventSolutions\NeventoSocialite\NeventoServiceProvider;
use EventSolutions\NeventoSocialite\Tests\Fixtures\TestUser;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->createUsersTable();
    }

    /**
     * @return array<int, class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [
            \Laravel\Socialite\SocialiteServiceProvider::class,
            NeventoServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);

        $app['config']->set('auth.providers.users.model', TestUser::class);
        $app['config']->set('services.nevento', [
            'client_id' => 'test-client',
            'client_secret' => 'test-secret',
            'redirect' => 'https://app.test/auth/callback',
            'host' => 'https://idp.test',
        ]);
    }

    /**
     * Mirrors the columns the package expects a host app to have added
     * (see the idp_id / workspace_role migrations in voer, eventsolutions, ...).
     */
    private function createUsersTable(): void
    {
        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('name')->nullable();
            $table->string('email')->unique();
            $table->string('idp_id')->nullable()->index();
            $table->string('workspace_role')->nullable();
            $table->timestamps();
        });
    }
}
