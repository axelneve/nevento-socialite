<?php

declare(strict_types=1);

namespace EventSolutions\NeventoSocialite\Tests;

use EventSolutions\NeventoSocialite\System\ReportsToSystem;
use EventSolutions\NeventoSocialite\System\SystemClient;
use Illuminate\Console\Events\CommandFinished;
use Illuminate\Console\Events\ScheduledTaskFailed;
use Illuminate\Console\Events\ScheduledTaskFinished;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Http\Client\Request;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\Mailer\Exception\TransportException;

class SystemReportingTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        SystemClient::resetBackoff();
        config(['nevento-system.url' => 'https://system.test', 'nevento-system.token' => 'sys_secret']);
    }

    private function listen(): void
    {
        app(ReportsToSystem::class)->subscribe(app('events'));
    }

    public function test_it_is_off_without_a_token(): void
    {
        config(['nevento-system.token' => null]);
        Http::fake();

        $this->assertFalse(app(SystemClient::class)->heartbeat('x', 'ok'));
        Http::assertNothingSent();
    }

    public function test_a_heartbeat_is_posted_with_the_token_and_without_empty_fields(): void
    {
        Http::fake(['system.test/*' => Http::response('', 204)]);

        $this->assertTrue(app(SystemClient::class)->heartbeat('backup:run', 'ok', null, 1500, null, 1440));

        Http::assertSent(fn (Request $request) => $request->url() === 'https://system.test/api/v1/heartbeats'
            && $request->hasHeader('Authorization', 'Bearer sys_secret')
            && $request->data() === ['task' => 'backup:run', 'status' => 'ok', 'duration_ms' => 1500, 'expected_every_minutes' => 1440]);
    }

    public function test_a_down_system_never_throws_and_is_not_hammered(): void
    {
        Http::fake(['system.test/*' => Http::response('', 500)]);
        $client = app(SystemClient::class);

        $this->assertFalse($client->heartbeat('a', 'ok'));
        $this->assertFalse($client->heartbeat('b', 'ok'));

        Http::assertSentCount(1);
    }

    public function test_a_finished_scheduled_command_is_reported_with_its_cadence(): void
    {
        Http::fake(['system.test/*' => Http::response('', 204)]);
        $this->listen();

        $task = (new Schedule)->command('backup:run')->dailyAt('01:30');
        $task->exitCode = 0;
        Event::dispatch(new ScheduledTaskFinished($task, 2.5));

        Http::assertSent(fn (Request $request) => str_ends_with($request->url(), '/heartbeats')
            && $request['task'] === 'backup:run' && $request['status'] === 'ok'
            && $request['duration_ms'] === 2500 && $request['expected_every_minutes'] === 1440);
    }

    public function test_a_non_zero_exit_code_is_a_failure(): void
    {
        Http::fake(['system.test/*' => Http::response('', 204)]);
        $this->listen();

        $task = (new Schedule)->command('backup:run')->hourly();
        $task->exitCode = 1;
        Event::dispatch(new ScheduledTaskFinished($task, 1.0));

        Http::assertSent(fn (Request $request) => $request['status'] === 'failed' && $request['message'] === 'exitcode 1');
    }

    public function test_a_closure_that_throws_is_a_failure(): void
    {
        Http::fake(['system.test/*' => Http::response('', 204)]);
        $this->listen();

        $task = (new Schedule)->call(fn () => null)->name('prune-things')->daily();
        Event::dispatch(new ScheduledTaskFailed($task, new \RuntimeException('kapot')));

        Http::assertSent(fn (Request $request) => $request['task'] === 'prune-things' && $request['status'] === 'failed' && $request['message'] === 'kapot');
    }

    public function test_ignored_tasks_are_not_reported(): void
    {
        config(['nevento-system.ignore' => ['queue:*']]);
        Http::fake();
        $this->listen();

        $task = (new Schedule)->command('queue:work')->everyMinute();
        $task->exitCode = 0;
        Event::dispatch(new ScheduledTaskFinished($task, 1.0));

        Http::assertNothingSent();
    }

    public function test_a_mail_transport_error_in_the_log_is_a_mail_failure_signal(): void
    {
        Http::fake(['system.test/*' => Http::response('', 204)]);
        $this->listen();

        Event::dispatch(new MessageLogged('error', 'boom', ['exception' => new TransportException('Resend 500')]));
        Event::dispatch(new MessageLogged('error', 'andere fout', ['exception' => new \RuntimeException('niets met mail')]));

        Http::assertSentCount(1);
        Http::assertSent(fn (Request $request) => str_ends_with($request->url(), '/signals') && $request['kind'] === 'mail_failure');
    }

    public function test_a_listener_that_blows_up_does_not_reach_the_app(): void
    {
        Http::fake(fn () => throw new \RuntimeException('network down'));
        $this->listen();

        $task = (new Schedule)->command('backup:run')->hourly();
        $task->exitCode = 0;
        Event::dispatch(new ScheduledTaskFinished($task, 1.0));

        $this->addToAssertionCount(1);
    }

    public function test_a_scheduled_tenant_command_is_reported_per_tenant_from_its_own_exit_code(): void
    {
        Http::fake(['system.test/*' => Http::response('', 204)]);
        app(Schedule::class)->command('tenants:run people:reconcile')->dailyAt('05:45');

        // A host app in tenant context: stancl's tenancy() says which tenant is active.
        $reporter = new class(app(SystemClient::class)) extends ReportsToSystem
        {
            protected function tenantKey(): ?string
            {
                return 'ws-2';
            }
        };
        $reporter->subscribe(app('events'));

        Event::dispatch(new CommandFinished('people:reconcile', new ArrayInput([]), new NullOutput, 1));
        Event::dispatch(new CommandFinished('people:reconcile', new ArrayInput([]), new NullOutput, 0));
        Event::dispatch(new CommandFinished('migrate', new ArrayInput([]), new NullOutput, 0));

        Http::assertSentCount(2);
        Http::assertSent(fn (Request $request) => $request['task'] === 'people:reconcile' && $request['tenant'] === 'ws-2'
            && $request['status'] === 'failed' && $request['expected_every_minutes'] === 1440);
        Http::assertSent(fn (Request $request) => $request['tenant'] === 'ws-2' && $request['status'] === 'ok');
    }

    public function test_outside_a_tenant_a_command_is_not_reported_per_tenant(): void
    {
        Http::fake();
        app(Schedule::class)->command('tenants:run people:reconcile')->dailyAt('05:45');
        $this->listen(); // no stancl in these tests: no tenant is active

        Event::dispatch(new CommandFinished('people:reconcile', new ArrayInput([]), new NullOutput, 0));

        Http::assertNothingSent();
    }
}
