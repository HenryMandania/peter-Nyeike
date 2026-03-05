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

class MpesaStkPushJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $purchase;

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
    public function handle()
    {
        try {
            $mpesa = new MpesaService();
            $result = $mpesa->processPayment($this->purchase);

            if (!$result['status']) {
                Log::error("M-Pesa STK Push Failed for Purchase #{$this->purchase->id}: " . $result['message']);
            } else {
                Log::info("M-Pesa STK Push Sent for Purchase #{$this->purchase->id}");
            }
        } catch (\Throwable $e) {
            Log::error("M-Pesa Job Exception for Purchase #{$this->purchase->id}: " . $e->getMessage());
        }
    }
}