<?php

namespace App\Jobs;

use App\Models\MpesaTransaction;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class ProcessMpesaCallbackJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $payload;

    public function __construct(array $payload)
    {
        $this->payload = $payload;
    }

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

        // Extract metadata safely
        $metadata = $callbackData['CallbackMetadata']['Item'] ?? [];
        $receipt = collect($metadata)->where('Name', 'MpesaReceiptNumber')->first()['Value'] ?? null;

        if ($callbackData['ResultCode'] == 0) {
            // Update MpesaTransaction record
            $transaction->update([
                'status' => 'completed',
                'mpesa_receipt_number' => (string)$receipt,
                'result_desc' => $callbackData['ResultDesc'],
                'raw_callback_payload' => json_encode($this->payload),
                'completed_at' => now(),
            ]);

            // Update Purchase record using DB facade to bypass potential model events
            $record = $transaction->transactionable;
            if ($record) {
                Log::info("DEBUG: Attempting raw DB update for Purchase ID: {$record->id}");
                
                DB::table('purchases')
                    ->where('id', $record->id)
                    ->update([
                        'status' => 'paid',
                        'mpesa_receipt_number' => (string)$receipt,
                    ]);
                
                Log::info("ProcessMpesaCallbackJob: Purchase {$record->id} updated to 'paid' successfully.");
            }
        } else {
            // Update as failed
            $transaction->update([
                'status' => 'failed',
                'result_desc' => $callbackData['ResultDesc'] ?? 'User Cancelled',
                'raw_callback_payload' => json_encode($this->payload),
            ]);
            Log::warning("ProcessMpesaCallbackJob: M-Pesa Payment Failed: {$callbackData['ResultDesc']}");
        }
    }
}