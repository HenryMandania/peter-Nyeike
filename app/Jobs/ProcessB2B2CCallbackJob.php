<?php

namespace App\Jobs;

use App\Models\Purchase;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessB2B2CCallbackJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected array $payload;

    public function __construct(array $payload)
    {
        $this->payload = $payload;
    }

    public function handle(): void
    {
        $result = $this->payload['Result'] ?? null;
        if (!$result) return;

        $conversationID = $result['ConversationID'] ?? '';
        $resultCode = $result['ResultCode'];

        // Find the purchase using the ConversationID
        $purchase = Purchase::where('conversation_id', $conversationID)->first();
        
        if (!$purchase) return;

        if ($resultCode == 0) {
            // Success: Extract Receipt from ResultParameters
            $params = $result['ResultParameters']['ResultParameter'] ?? [];
            $receipt = collect($params)->where('Key', 'ReceiptNumber')->first()['Value'] ?? 'N/A';

            $purchase->update([
                'status' => 'paid',
                'payment_status' => 'paid',
                'mpesa_receipt_number' => $receipt
            ]);
        } else {
            // Failure
            $purchase->update(['payment_status' => 'failed']);
        }
    }
}