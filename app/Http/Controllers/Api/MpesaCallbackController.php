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
    // 1. Better way to get data in Laravel
    $data = $request->all();
    
    // Log the raw data so you can debug in storage/logs/laravel.log
    Log::info('Mpesa Callback Payload:', $data);

    // Guard against empty or malformed body
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
        // SUCCESS logic
        $metadata = $callbackData['CallbackMetadata']['Item'] ?? [];
        $receipt = null;

        // Extract receipt number
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
            'raw_callback_payload' => json_encode($data), // Store as string
            'completed_at' => now(),
        ]);

        $record = $transaction->transactionable;
        if ($record) {
            $record->update([
                'status' => 'paid', // Use 'paid' or 'approved' based on your Purchase model
                'mpesa_receipt_number' => $receipt,
            ]);

            $this->updateShiftBalance($record, $transaction->type);
        }

        Log::info("M-Pesa Payment Success: {$receipt}");

    } else {
        // FAILED logic
        $transaction->update([
            'status' => 'failed',
            'result_desc' => $callbackData['ResultDesc'] ?? 'User Cancelled',
            'raw_callback_payload' => json_encode($data),
        ]);
        
        Log::warning("M-Pesa Payment Failed for {$checkoutID}: " . ($callbackData['ResultDesc'] ?? 'Unknown'));
    }

    // Safaricom likes this exact response
    return response()->json([
        'ResponseCode' => '00000000',
        'ResponseDesc' => 'success'
    ]);
}
}