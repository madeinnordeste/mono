<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class ExampleJob implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public readonly string $message = 'Hello from the queue',
        public readonly int $seconds = 1,
    ) {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        if ($this->seconds > 0) {
            sleep($this->seconds);
        }

        Log::info('Example job processed.', [
            'message' => $this->message,
            'worker_pid' => getmypid(),
        ]);
    }
}
