<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\MpesaService;
use App\Models\Purchase;
use Illuminate\Http\Request;
use App\Models\MpesaTransaction;
use App\Models\FloatRequest;

class PaymentController extends Controller
{
    protected $mpesaService;

    public function __construct(MpesaService $mpesaService)
    {
        $this->mpesaService = $mpesaService;
    }

    public function initiateVendorPayment(Request $request)
    {
        $request->validate(['purchase_id' => 'required|exists:purchases,id']);

        try {
            $purchase = Purchase::findOrFail($request->purchase_id);
            $result = $this->mpesaService->processVendorPayment($purchase);

            return response()->json([
                'success' => true,
                'message' => 'Payment request sent to M-Pesa.',
                'data' => $result
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function capturePurchaseMpesaMessage(Request $request)
{
    $request->validate([
        'purchase_id' => 'required|exists:purchases,id',
        'message' => 'required|string'
    ]);

    $purchase = Purchase::findOrFail($request->purchase_id);
    $message = $request->message;

    // 🔹 Check if purchase already has completed payment
    $existingPayment = $purchase->mpesaTransactions()
        ->where('status', 'completed')
        ->first();

    if ($existingPayment) {
        return response()->json([
            'success' => false,
            'message' => 'Payment already updated',
            'mpesa_code' => $existingPayment->mpesa_receipt_number
        ]);
    }

    try {

        // Parse SMS
        preg_match('/^([A-Z0-9]+)/', $message, $code);
        preg_match('/Ksh([\d,]+\.\d{2})/', $message, $amount);
        preg_match('/from\s(.+?)\sYour/i', $message, $name);
        preg_match('/On\s(.+?)\sTake/i', $message, $datetime);

        $mpesaCode = $code[1] ?? null;
        $amountValue = isset($amount[1]) ? str_replace(',', '', $amount[1]) : null;
        $customerName = $name[1] ?? null;
        $timeValue = $datetime[1] ?? null;

        if (!$mpesaCode) {
            return response()->json([
                'success' => false,
                'message' => 'Unable to parse MPESA message'
            ], 422);
        }

        // Prevent duplicate MPESA code
        if (MpesaTransaction::where('mpesa_receipt_number', $mpesaCode)->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'MPESA transaction already recorded'
            ]);
        }

        // Create transaction
        $transaction = MpesaTransaction::create([
            'transactionable_type' => Purchase::class,
            'transactionable_id' => $purchase->id,
            'type' => 'purchase',
            'mpesa_receipt_number' => $mpesaCode,
            'checkout_request_id' => uniqid('SMS_'),
            'amount' => $amountValue,
            'phone_number' => '0',
            'status' => 'completed',
            'result_desc' => $customerName,
            'completed_at' => now(),
            'raw_callback_payload' => json_encode([
                'sms' => $message,
                'name' => $customerName,
                'time' => $timeValue
            ])
        ]);

        return response()->json([
            'success' => true,
            'message' => 'MPESA payment recorded successfully',
            'data' => [
                'purchase_id' => $purchase->id,
                'mpesa_code' => $mpesaCode,
                'amount' => $amountValue
            ]
        ]);

    } catch (\Exception $e) {

        return response()->json([
            'success' => false,
            'message' => $e->getMessage()
        ], 500);

    }
}



public function captureFloatRequestMpesaMessage(Request $request)
{
    $request->validate([
        'float_request_id' => 'required|exists:float_requests,id',
        'message' => 'required|string'
    ]);

    $floatRequest = FloatRequest::findOrFail($request->float_request_id);
    $message = $request->message;

    // Check if already paid
    $existingPayment = $floatRequest->mpesaTransactions()
        ->where('status', 'completed')
        ->first();

    if ($existingPayment) {
        return response()->json([
            'success' => false,
            'message' => 'Payment already updated',
            'mpesa_code' => $existingPayment->mpesa_receipt_number
        ]);
    }

    try {

        // Parse SMS
        preg_match('/^([A-Z0-9]+)/', $message, $code);
        preg_match('/Ksh([\d,]+\.\d{2})/', $message, $amount);
        preg_match('/from\s(.+?)\sYour/i', $message, $name);
        preg_match('/On\s(.+?)\sTake/i', $message, $datetime);

        $mpesaCode = $code[1] ?? null;
        $amountValue = isset($amount[1]) ? str_replace(',', '', $amount[1]) : null;
        $customerName = $name[1] ?? null;
        $timeValue = $datetime[1] ?? null;

        if (!$mpesaCode) {
            return response()->json([
                'success' => false,
                'message' => 'Unable to parse MPESA message'
            ], 422);
        }

        // Prevent duplicate Mpesa code
        if (MpesaTransaction::where('mpesa_receipt_number', $mpesaCode)->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'MPESA transaction already recorded'
            ]);
        }

        $transaction = MpesaTransaction::create([
            'transactionable_type' => FloatRequest::class,
            'transactionable_id' => $floatRequest->id,
            'type' => 'float_request',
            'mpesa_receipt_number' => $mpesaCode,
            'checkout_request_id' => uniqid('SMS_'),
            'amount' => $amountValue,
            'phone_number' => '0',
            'status' => 'completed',
            'result_desc' => $customerName,
            'completed_at' => now(),
            'raw_callback_payload' => json_encode([
                'sms' => $message,
                'name' => $customerName,
                'time' => $timeValue
            ])
        ]);

        // Update float request to funded
        $floatRequest->update([
            'status' => 'funded'
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Float request payment recorded successfully',
            'data' => [
                'float_request_id' => $floatRequest->id,
                'mpesa_code' => $mpesaCode,
                'amount' => $amountValue
            ]
        ]);

    } catch (\Exception $e) {

        return response()->json([
            'success' => false,
            'message' => $e->getMessage()
        ], 500);
    }
}


}