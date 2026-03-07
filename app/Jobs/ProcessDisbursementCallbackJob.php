<?php

namespace App\Jobs;

use App\Models\Purchase;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessDisbursementCallbackJob implements ShouldQueue
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
        $resultCode = $result['ResultCode'] ?? 1;

        $purchase = Purchase::where('conversation_id', $conversationID)->first();
        if (!$purchase) {
            Log::warning("No matching purchase for ConversationID: {$conversationID}");
            return;
        }

        if ($resultCode == 0) {
            $params = collect($result['ResultParameters']['ResultParameter'] ?? []);
            $receipt = $params->firstWhere('Key', 'ReceiptNumber')['Value'] ?? 'N/A';

            $purchase->update([
                'status' => 'paid',
                'payment_status' => 'paid',
                'mpesa_receipt_number' => $receipt,
            ]);
            Log::info("Purchase {$purchase->id} paid successfully via M-Pesa");
        } else {
            $purchase->update(['payment_status' => 'failed']);
            Log::warning("Purchase {$purchase->id} failed: " . ($result['ResultDesc'] ?? 'Unknown error'));
        }
    }
}