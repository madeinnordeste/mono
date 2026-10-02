<?php

namespace Tests\Feature\Console;

use App\Jobs\ExampleJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class ProcessQueueTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('queue.default', 'database');
    }

    public function test_pending_jobs_are_processed(): void
    {
        Log::spy();

        ExampleJob::dispatch('primeiro', 0);
        ExampleJob::dispatch('segundo', 0);

        $this->artisan('app:process-queue', ['--sleep' => 0])
            ->expectsOutputToContain('Drained 2 job(s)')
            ->assertSuccessful();

        Log::shouldHaveReceived('info')
            ->with('Example job processed.', Mockery::on(fn (array $context): bool => $context['message'] === 'primeiro'));

        Log::shouldHaveReceived('info')
            ->with('Example job processed.', Mockery::on(fn (array $context): bool => $context['message'] === 'segundo'));

        $this->assertSame(0, DB::table('jobs')->count());
    }

    public function test_exits_successfully_when_the_queue_is_empty(): void
    {
        $this->artisan('app:process-queue', ['--sleep' => 0])
            ->expectsOutputToContain('Drained 0 job(s)')
            ->assertSuccessful();
    }

    public function test_reports_the_failure_when_a_job_throws(): void
    {
        dispatch(function (): void {
            throw new RuntimeException('boom');
        });

        $this->artisan('app:process-queue', ['--sleep' => 0])
            ->expectsOutputToContain('failed: boom')
            ->expectsOutputToContain('1 job(s) failed')
            ->assertFailed();
    }
}
