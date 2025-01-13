<?php

namespace Acelle\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Exception;

class SyncMailListsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     *
     * @var int
     */
    public $tries = 3;

    /**
     * The number of seconds to wait before retrying the job.
     *
     * @var int
     */
    public $backoff = 60;

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        try {
            Log::info('Starting mail lists synchronization...');

            $exitCode = Artisan::call('sync:maillists');

            if ($exitCode !== 0) {
                throw new Exception('Command sync:maillists failed with exit code: ' . $exitCode);
            }

            Log::info('Mail lists synchronization completed successfully.');
        } catch (Exception $e) {
            Log::error('Mail lists synchronization failed: ' . $e->getMessage());

            // Determine if we should retry
            if ($this->attempts() >= $this->tries) {
                Log::error('Max retry attempts reached. Failing job permanently.');
                $this->fail($e);
            } else {
                throw $e; // This will trigger a retry based on the backoff setting
            }
        }
    }

    /**
     * Handle a job failure.
     *
     * @param  \Throwable  $exception
     * @return void
     */
    public function failed(\Throwable $exception)
    {
        Log::error('SyncMailListsJob failed permanently: ' . $exception->getMessage());

        // Add any cleanup or notification logic here
        // For example, you could send an email to admin or update a status in database
    }
}