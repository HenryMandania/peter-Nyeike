<?php

namespace App\Jobs;

use App\Models\MpesaTransaction;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessMpesaCallbackJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $payload;

    /**
     * Create a new job instance.
     */
    public function __construct(array $payload)
    {
        $this->payload = $payload;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $callbackData = $this->payload['Body']['stkCallback'] ?? null;

        if (!$callbackData) {
            Log::error("ProcessMpesaCallbackJob: Missing stkCallback data.");
            return;
        }

        $checkoutID = $callbackData['CheckoutRequestID'];
        $transaction = MpesaTransaction::where('checkout_request_id', $checkoutID)->first();

        if (!$transaction) {
            Log::error("ProcessMpesaCallbackJob: Transaction not found for ID: " . $checkoutID);
            return;
        }

        if ($callbackData['ResultCode'] == 0) {
            // Success: Extract receipt
            $metadata = $callbackData['CallbackMetadata']['Item'] ?? [];
            $receipt = collect($metadata)->where('Name', 'MpesaReceiptNumber')->first()['Value'] ?? null;

            // Update Transaction
            $transaction->update([
                'status' => 'completed',
                'mpesa_receipt_number' => $receipt,
                'result_desc' => $callbackData['ResultDesc'],
                'raw_callback_payload' => json_encode($this->payload),
                'completed_at' => now(),
            ]);

            // Update related Purchase record
            $record = $transaction->transactionable;
            if ($record) {
                $record->update([
                    'status' => 'paid',
                    'mpesa_receipt_number' => $receipt,
                ]);
            }

            Log::info("ProcessMpesaCallbackJob: M-Pesa Payment Success for Receipt: {$receipt}");
        } else {
            // Failure
            $transaction->update([
                'status' => 'failed',
                'result_desc' => $callbackData['ResultDesc'] ?? 'User Cancelled',
                'raw_callback_payload' => json_encode($this->payload),
            ]);

            Log::warning("ProcessMpesaCallbackJob: M-Pesa Payment Failed for {$checkoutID}");
        }
    }
}