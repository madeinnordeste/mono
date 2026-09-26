<?php

namespace App\Console\Commands;

use App\Jobs\ExampleJob;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('app:dispatch-example-job {--count=1 : How many jobs to enqueue} {--seconds=1 : How many seconds each job pretends to work}')]
#[Description('Enqueue example jobs so the queue can be observed in action')]
class DispatchExampleJob extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $count = max(1, (int) $this->option('count'));
        $seconds = max(0, (int) $this->option('seconds'));

        for ($position = 1; $position <= $count; $position++) {
            ExampleJob::dispatch("Job {$position} de {$count}", $seconds);
        }

        $connection = (string) config('queue.default');
        $queue = (string) config("queue.connections.{$connection}.queue", 'default');

        $this->components->info(sprintf(
            'Enqueued %d job(s) on the [%s] %s queue.',
            $count,
            $connection,
            $queue,
        ));

        $this->components->twoColumnDetail('Process them', 'php artisan app:process-queue');

        return self::SUCCESS;
    }
}
