<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\MpesaTransaction;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class MpesaCallbackController extends Controller
{
    public function handle(Request $request)
    {
        $data = $request->all();
        Log::info('Mpesa Callback Payload:', $data);

        if (!isset($data['Body']['stkCallback'])) {
            Log::error("M-Pesa Callback: Missing Body/stkCallback");
            return response()->json(['ResultCode' => 1, 'ResultDesc' => 'Invalid Payload']);
        }

        $callbackData = $data['Body']['stkCallback'];
        $checkoutID = $callbackData['CheckoutRequestID'];

        // Wrapped in a transaction to prevent database locks/hanging
        return DB::transaction(function () use ($callbackData, $checkoutID, $data) {
            $transaction = MpesaTransaction::where('checkout_request_id', $checkoutID)
                ->lockForUpdate()
                ->first();

            if (!$transaction) {
                Log::error("M-Pesa Callback received for unknown CheckoutID: " . $checkoutID);
                return response()->json(['ResultCode' => 1, 'ResultDesc' => 'Transaction not found']);
            }

            $resultCode = $callbackData['ResultCode'];
            $status = ($resultCode == 0) ? 'completed' : 'failed';

            if ($status === 'completed') {
                $metadata = $callbackData['CallbackMetadata']['Item'] ?? [];
                $receipt = collect($metadata)->firstWhere('Name', 'MpesaReceiptNumber')['Value'] ?? null;

                $transaction->update([
                    'status' => 'completed',
                    'mpesa_receipt_number' => $receipt,
                    'result_desc' => $callbackData['ResultDesc'],
                    'raw_callback_payload' => json_encode($data),
                    'completed_at' => now(),
                ]);

                // Update the purchase status only
                $record = $transaction->transactionable;
                if ($record) {
                    $record->update([
                        'status' => 'paid',
                        'mpesa_receipt_number' => $receipt,
                    ]);
                }
                
                Log::info("M-Pesa Payment Success: {$receipt}. Balance update skipped as per business logic.");

            } else {
                $transaction->update([
                    'status' => 'failed',
                    'result_desc' => $callbackData['ResultDesc'] ?? 'User Cancelled',
                    'raw_callback_payload' => json_encode($data),
                ]);
                Log::warning("M-Pesa Payment Failed for {$checkoutID}: " . ($callbackData['ResultDesc'] ?? 'Unknown'));
            }

            return response()->json([
                'ResponseCode' => '00000000',
                'ResponseDesc' => 'success'
            ]);
        });
    }
}