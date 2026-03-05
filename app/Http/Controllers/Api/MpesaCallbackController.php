<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\MpesaTransaction;
use Illuminate\Support\Facades\Log;

class MpesaCallbackController extends Controller
{
    public function handle(Request $request)
    {
        $data = json_decode($request->getContent(), true);
        
        $callbackData = $data['Body']['stkCallback'];
        $resultCode = $callbackData['ResultCode'];
        $checkoutID = $callbackData['CheckoutRequestID'];

        // 1. Find the log in our centralized table
        $transaction = MpesaTransaction::where('checkout_request_id', $checkoutID)->first();

        if (!$transaction) {
            Log::error("M-Pesa Callback received for unknown CheckoutID: " . $checkoutID);
            return response()->json(['ResultCode' => 1, 'ResultDesc' => 'Transaction not found']);
        }

        if ($resultCode == 0) {
            // SUCCESS
            $metadata = $callbackData['CallbackMetadata']['Item'];
            $receipt = collect($metadata)->firstWhere('Name', 'MpesaReceiptNumber')['Value'] ?? null;

            // 2. Update the Centralized Transaction Log
            $transaction->update([
                'status' => 'completed',
                'mpesa_receipt_number' => $receipt,
                'result_desc' => $callbackData['ResultDesc'],
                'raw_callback_payload' => $data,
                'completed_at' => now(),
            ]);

            // 3. Update the Linked Model (Purchase, Expense, or Float)
            $record = $transaction->transactionable;
            if ($record) {
                $record->update([
                    'status' => 'approved', // or 'paid'
                    'mpesa_receipt_number' => $receipt,
                ]);

                // 4. Update Shift Balance Logic
                $this->updateShiftBalance($record, $transaction->type);
            }

            Log::info("M-Pesa Payment Successful: {$receipt} for {$transaction->type} #{$record->id}");

        } else {
            // FAILED (Cancelled or Insufficient Funds)
            $transaction->update([
                'status' => 'failed',
                'result_desc' => $callbackData['ResultDesc'],
                'raw_callback_payload' => $data,
            ]);
            
            Log::warning("M-Pesa Payment Failed: " . $callbackData['ResultDesc']);
        }

        return response()->json(['ResultCode' => 0, 'ResultDesc' => 'Success']);
    }

    /**
     * Logic to adjust the operator's shift balance
     */
    protected function updateShiftBalance($record, $type)
    {
        // Safety check: ensure the record has a relationship to a 'shift'
        if (!$record->shift) return;

        $amount = $record->amount ?? $record->total_amount;

        if ($type === 'float_request') {
            // Operator paid the business -> Increase their balance
            $record->shift->increment('current_balance', $amount);
        } else {
            // Purchase or Expense -> Business paid out -> Decrease operator's held balance
            $record->shift->decrement('current_balance', $amount);
        }
    }
}