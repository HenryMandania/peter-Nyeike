<?php

namespace App\Jobs;

use App\Models\MpesaTransaction;
use App\Models\Purchase;
use App\Models\FloatRequest;
use App\Models\Expense;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

class ProcessMpesaCallbackJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected array $payload;
    public $tries = 3;
    public $backoff = 5;

    public function __construct(array $payload)
    {
        $this->payload = $payload;
    }

    public function handle(): void
    {
        $callback = $this->payload['Body']['stkCallback'] ?? null;

        if (!$callback) {
            return;
        }

        $checkoutID = trim($callback['CheckoutRequestID'] ?? '');
        $resultCode = $callback['ResultCode'] ?? 1;
        $resultDesc = $callback['ResultDesc'] ?? '';

        try {
            $transaction = MpesaTransaction::where('checkout_request_id', $checkoutID)->first();

            if (!$transaction) {
                return;
            }

            DB::transaction(function () use ($transaction, $callback, $resultCode, $resultDesc) {
                
                $lockedTransaction = MpesaTransaction::where('id', $transaction->id)
                    ->lockForUpdate()
                    ->first();

                if (!$lockedTransaction || in_array($lockedTransaction->status, ['completed', 'failed'])) {
                    return;
                }

                if ($resultCode == 0) {
                    $this->handleSuccessfulPayment($lockedTransaction, $callback);
                } else {
                    $this->handleFailedPayment($lockedTransaction, $resultDesc);
                }
            });
            
        } catch (\Exception $e) {
            // Log only critical errors, or use your error tracking service
        }
    }

    protected function handleSuccessfulPayment($transaction, $callback)
    {
        $metadata = $callback['CallbackMetadata']['Item'] ?? [];
        
        $receipt = null;
        foreach ($metadata as $item) {
            if ($item['Name'] === 'MpesaReceiptNumber') {
                $receipt = $item['Value'] ?? null;
                break;
            }
        }
        
        if (!$receipt) {
            return;
        }

        // Update transaction
        $transaction->update([
            'status' => 'completed',
            'mpesa_receipt_number' => $receipt,
            'result_desc' => $callback['ResultDesc'] ?? 'Success',
            'raw_callback_payload' => json_encode($this->payload),
            'completed_at' => now(),
        ]);

        // Update the related record
        $record = $transaction->transactionable;
        if ($record) {
            if ($record instanceof Purchase) {
                $record->update([
                    'status' => 'paid',
                    'payment_status' => 'paid',
                    'mpesa_receipt_number' => $receipt,
                    'mpesa_error_message' => null,
                ]);
                
            } elseif ($record instanceof FloatRequest) {
                $record->update([
                    'status' => 'paid',
                    'mpesa_receipt_number' => $receipt,
                ]);
                
            } elseif ($record instanceof Expense) {
                $record->update([
                    'status' => 'paid',
                    'mpesa_receipt_number' => $receipt,
                ]);
            }
        }
    }

    protected function handleFailedPayment($transaction, $resultDesc)
    {
        $transaction->update([
            'status' => 'failed',
            'result_desc' => $resultDesc,
            'raw_callback_payload' => json_encode($this->payload),
        ]);
        
        $record = $transaction->transactionable;
        if ($record && $record instanceof Purchase) {
            $record->update([
                'payment_status' => 'failed',
                'mpesa_error_message' => $resultDesc,
            ]);
        }
    }
}