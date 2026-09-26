<?php

namespace Tests\Feature\Console;

use App\Jobs\ExampleJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class DispatchExampleJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_dispatches_a_single_job_by_default(): void
    {
        Queue::fake();

        $this->artisan('app:dispatch-example-job')
            ->expectsOutputToContain('Enqueued 1 job(s)')
            ->assertSuccessful();

        Queue::assertPushed(ExampleJob::class, 1);
    }

    public function test_dispatches_the_requested_number_of_jobs(): void
    {
        Queue::fake();

        $this->artisan('app:dispatch-example-job', ['--count' => 3, '--seconds' => 0])
            ->expectsOutputToContain('Enqueued 3 job(s)')
            ->assertSuccessful();

        Queue::assertPushed(ExampleJob::class, 3);
    }

    public function test_enqueues_at_least_one_job_when_the_count_is_below_one(): void
    {
        Queue::fake();

        $this->artisan('app:dispatch-example-job', ['--count' => 0])
            ->expectsOutputToContain('Enqueued 1 job(s)')
            ->assertSuccessful();

        Queue::assertPushed(ExampleJob::class, 1);
    }
}
