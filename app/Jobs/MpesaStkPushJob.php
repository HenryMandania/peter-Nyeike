<?php

namespace App\Jobs;

use App\Models\Purchase;
use App\Services\MpesaService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class MpesaStkPushJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $purchase;

    /**
     * The number of times the job may be attempted.
     * We set this to 3 to handle transient network timeouts.
     */
    public $tries = 3;

    /**
     * The number of seconds to wait before retrying the job.
     * [10s, 30s, 60s] gives the network tunnel time to clear stuck sockets.
     */
    public $backoff = [10, 30, 60];

    /**
     * Create a new job instance.
     */
    public function __construct(Purchase $purchase)
    {
        $this->purchase = $purchase;
    }

    /**
     * Execute the job.
     */
    public function handle(MpesaService $mpesaService)
    {
        Log::info("Starting M-Pesa STK Push Job for Purchase #{$this->purchase->id}");

        $result = $mpesaService->processPayment($this->purchase);

        if (!$result['status']) {
            // Throwing an exception triggers the $tries and $backoff logic
            Log::warning("M-Pesa API returned failure for Purchase #{$this->purchase->id}: " . $result['message']);
            throw new \Exception("M-Pesa API request failed: " . $result['message']);
        }

        Log::info("M-Pesa STK Push successfully queued for Purchase #{$this->purchase->id}");
    }

    /**
     * Handle a job failure.
     */
    public function failed(Throwable $exception)
    {
        Log::error("M-Pesa STK Push Job permanently failed for Purchase #{$this->purchase->id}: " . $exception->getMessage());
        
        // Optional: Update purchase status to 'failed' in DB if it persists
        $this->purchase->update(['status' => 'failed']);
    }
}