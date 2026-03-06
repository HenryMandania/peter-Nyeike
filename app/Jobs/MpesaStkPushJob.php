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

    public $purchaseId;
    public $tries = 3;
    public $backoff = 10; // Wait 10 seconds between retries

    public function __construct($purchaseId)
    {
        $this->purchaseId = $purchaseId;
    }

    public function handle(MpesaService $mpesaService)
{
    Log::info("MpesaStkPushJob: Processing purchase ID: {$this->purchaseId}");

    $purchase = Purchase::with('vendor')->find($this->purchaseId);

    if (!$purchase) {
        Log::error("MpesaStkPushJob: Purchase not found {$this->purchaseId}");
        return;
    }

    // Check if already paid
    if ($purchase->status === 'paid' || $purchase->mpesa_receipt_number) {
        Log::info("MpesaStkPushJob: Purchase {$this->purchaseId} already paid");
        return;
    }

    if ($purchase->status !== 'approved') {
        Log::warning("MpesaStkPushJob: Purchase not approved {$this->purchaseId}");
        return;
    }

    // Check for existing pending transaction
    if ($purchase->mpesa_checkout_id && $purchase->payment_status === 'processing') {
        // Check if it's been too long (e.g., more than 5 minutes)
        $existingTransaction = MpesaTransaction::where('checkout_request_id', $purchase->mpesa_checkout_id)
            ->where('status', 'requested')
            ->first();
            
        if ($existingTransaction && $existingTransaction->created_at->diffInMinutes(now()) < 5) {
            Log::info("MpesaStkPushJob: Payment already processing for purchase {$this->purchaseId}");
            return;
        }
    }

    $result = $mpesaService->processPayment($purchase);

    if (!$result['status']) {
        Log::error("MpesaStkPushJob: STK push failed - " . $result['message']);
        
        $purchase->update([
            'mpesa_error_message' => $result['message']
        ]);
    }
}
}