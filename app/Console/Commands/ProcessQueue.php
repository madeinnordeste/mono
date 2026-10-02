<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Queue\Events\WorkerStopping;
use Illuminate\Queue\WorkerStopReason;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Event;

#[Signature('app:process-queue
    {connection? : The queue connection to drain, defaults to queue.default}
    {--queue= : The queue to drain, defaults to the queue of the connection}
    {--name=default : The name of the worker}
    {--tries=1 : How many times a job is attempted before being marked as failed}
    {--timeout=60 : The number of seconds a single job may run}
    {--sleep=1 : The number of seconds to wait before re-checking an empty queue}')]
#[Description('Process every pending job on the queue, then exit')]
class ProcessQueue extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $connection = (string) ($this->argument('connection') ?: config('queue.default'));
        $queue = (string) ($this->option('queue') ?: config("queue.connections.{$connection}.queue", 'default'));

        $processed = 0;
        $failures = [];
        $stoppedBecause = '';
        $startedJobAt = 0.0;
        $drainedAt = microtime(true);

        $this->listenForJobEvents($startedJobAt, $failures);
        $this->listenForWorkerStop($processed, $stoppedBecause);

        $this->components->info(sprintf(
            'Draining queue [%s] on the [%s] connection.',
            $queue,
            $connection,
        ));

        $exitCode = Artisan::call('queue:work', [
            'connection' => $connection,
            '--queue' => $queue,
            '--name' => (string) $this->option('name'),
            '--tries' => (int) $this->option('tries'),
            '--timeout' => (int) $this->option('timeout'),
            '--sleep' => (int) $this->option('sleep'),
            '--stop-when-empty' => true,
        ]);

        $this->components->info(sprintf(
            'Drained %d job(s) in %.2fs (%s).',
            $processed,
            microtime(true) - $drainedAt,
            $stoppedBecause !== '' ? strtolower($stoppedBecause) : 'finished',
        ));

        if ($failures !== []) {
            $this->components->error(sprintf(
                '%d job(s) failed: %s',
                count($failures),
                implode(', ', array_unique($failures)),
            ));

            return self::FAILURE;
        }

        return $exitCode;
    }

    /**
     * Report each job as it starts, succeeds or fails.
     *
     * @param  array<int, string>  $failures
     */
    private function listenForJobEvents(float &$startedJobAt, array &$failures): void
    {
        Event::listen(JobProcessing::class, function (JobProcessing $event) use (&$startedJobAt): void {
            $startedJobAt = microtime(true);

            $this->components->twoColumnDetail($event->job->resolveName(), 'starting');
        });

        Event::listen(JobProcessed::class, function (JobProcessed $event) use (&$startedJobAt): void {
            $this->components->twoColumnDetail(
                $event->job->resolveName(),
                sprintf('done in %.2fs', microtime(true) - $startedJobAt),
            );
        });

        Event::listen(JobFailed::class, function (JobFailed $event) use (&$failures): void {
            $failures[] = $event->job->resolveName();

            $this->components->twoColumnDetail(
                $event->job->resolveName(),
                'failed: '.$event->exception->getMessage(),
            );
        });
    }

    /**
     * Capture how many jobs ran and why the worker stopped.
     */
    private function listenForWorkerStop(int &$processed, string &$stoppedBecause): void
    {
        Event::listen(WorkerStopping::class, function (WorkerStopping $event) use (&$processed, &$stoppedBecause): void {
            $processed = (int) $event->jobsProcessed;
            $stoppedBecause = $event->reason instanceof WorkerStopReason
                ? $event->reason->description()
                : '';
        });
    }
}
