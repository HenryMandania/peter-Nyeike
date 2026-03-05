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
        $data = $request->all();
        Log::info('Mpesa Callback Payload:', $data);

        if (!isset($data['Body']['stkCallback'])) {
            Log::error("M-Pesa Callback: Missing Body/stkCallback");
            return response()->json(['ResultCode' => 1, 'ResultDesc' => 'Invalid Payload']);
        }

        $callbackData = $data['Body']['stkCallback'];
        $resultCode = $callbackData['ResultCode'];
        $checkoutID = $callbackData['CheckoutRequestID'];

        $transaction = MpesaTransaction::where('checkout_request_id', $checkoutID)->first();

        if (!$transaction) {
            Log::error("M-Pesa Callback received for unknown CheckoutID: " . $checkoutID);
            return response()->json(['ResultCode' => 1, 'ResultDesc' => 'Transaction not found']);
        }

        if ($resultCode == 0) {
            $metadata = $callbackData['CallbackMetadata']['Item'] ?? [];
            $receipt = null;

            foreach ($metadata as $item) {
                if ($item['Name'] === 'MpesaReceiptNumber') {
                    $receipt = $item['Value'];
                    break;
                }
            }

            $transaction->update([
                'status' => 'completed',
                'mpesa_receipt_number' => $receipt,
                'result_desc' => $callbackData['ResultDesc'],
                'raw_callback_payload' => json_encode($data),
                'completed_at' => now(),
            ]);

            $record = $transaction->transactionable;
            if ($record) {
                $record->update([
                    'status' => 'paid',
                    'mpesa_receipt_number' => $receipt,
                ]);
            }

            Log::info("M-Pesa Payment Success: {$receipt}");
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
    }
}