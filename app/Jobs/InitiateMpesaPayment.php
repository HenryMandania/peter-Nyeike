<?php

namespace App\Jobs;

use App\Models\Purchase;
use App\Services\MpesaService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class InitiateMpesaPayment implements ShouldQueue
{
    use Queueable;

    protected $purchaseId;

    /**
     * Create a new job instance.
     */
    public function __construct($purchaseId)
    {
        $this->purchaseId = $purchaseId;
    }

    /**
     * Execute the job.
     */
    public function handle(MpesaService $mpesaService)
    {
        try {

            $purchase = Purchase::with('vendor')->find($this->purchaseId);

            if (!$purchase) {
                Log::error("MPESA JOB: Purchase not found {$this->purchaseId}");
                return;
            }

            if ($purchase->status !== 'approved') {
                Log::warning("MPESA JOB: Purchase not approved {$this->purchaseId}");
                return;
            }

            $result = $mpesaService->processPayment($purchase);

            if (!$result['status']) {
                Log::error("MPESA JOB FAILED: ".$result['message']);
            }

        } catch (\Throwable $e) {

            Log::error("MPESA QUEUE ERROR: ".$e->getMessage());

        }
    }
}