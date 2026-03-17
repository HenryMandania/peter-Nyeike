<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\MpesaService;
use App\Models\Purchase;
use Illuminate\Http\Request;
use App\Models\MpesaTransaction;
use App\Models\FloatRequest;
use Illuminate\Support\Facades\DB;

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
        'message' => 'required|string',
        'selling_unit_price' => 'nullable|numeric|min:0' 
    ]);

    // Include the shift relation to access company_id later
    $purchase = \App\Models\Purchase::with('shift')->findOrFail($request->purchase_id);
    $message = $request->message;

    // 1. Validation Checks (Status & Existing Payments)
    if ($purchase->status !== 'approved') {
        return response()->json(['success' => false, 'message' => 'Purchase must be approved before capturing payment.'], 422);
    }

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
        // 2. Parse SMS Logic
        preg_match('/^([A-Z0-9]+)/', $message, $code);
        preg_match('/Ksh([\d,]+\.\d{2})/', $message, $amount);
        preg_match('/from\s(.+?)\sYour/i', $message, $name);
        
        $mpesaCode = $code[1] ?? null;
        $amountValue = isset($amount[1]) ? str_replace(',', '', $amount[1]) : null;
        $customerName = $name[1] ?? null;

        if (!$mpesaCode) {
            return response()->json(['success' => false, 'message' => 'Unable to parse MPESA message'], 422);
        }

        // NEW: Check if the SMS amount matches the expected Purchase total
        if (floatval($amountValue) !== floatval($purchase->total_amount)) {
            return response()->json([
                'success' => false, 
                'message' => 'Amount does not match the payment',
                'expected' => $purchase->total_amount,
                'received' => $amountValue
            ], 422);
        }

        if (\App\Models\MpesaTransaction::where('mpesa_receipt_number', $mpesaCode)->exists()) {
            return response()->json(['success' => false, 'message' => 'MPESA transaction already recorded']);
        }

        // 3. Database Transaction: Payment + Selling Logic
        // Using \DB:: to avoid namespace issues if 'use' is missing
        return \DB::transaction(function () use ($purchase, $mpesaCode, $amountValue, $customerName, $message, $request) {
            
            // A. Record the M-Pesa Transaction
            $transaction = \App\Models\MpesaTransaction::create([
                'transactionable_type' => \App\Models\Purchase::class,
                'transactionable_id' => $purchase->id,
                'type' => 'purchase',
                'mpesa_receipt_number' => $mpesaCode,
                'checkout_request_id' => uniqid('SMS_'),
                'amount' => $amountValue,
                'phone_number' => '0',
                'status' => 'completed',
                'result_desc' => $customerName,
                'completed_at' => now(),
                'raw_callback_payload' => json_encode(['sms' => $message])
            ]);

            // B. Auto-Sell Logic
            if ($request->filled('selling_unit_price')) {
                $sellingPrice = floatval($request->selling_unit_price);
                $totalSales = $purchase->quantity * $sellingPrice;
                $profit = $totalSales - $purchase->total_amount;

                // Update the purchase record
                $purchase->update([
                    'selling_unit_price' => $sellingPrice,
                    'sales_amount' => $totalSales,
                    'gross_profit' => $profit,
                    'is_sold' => true,
                    'sold_at' => now(),
                    'sold_by' => auth()->id() ?? $purchase->created_by,
                ]);

                // Create the sale record
                \App\Models\Sale::create([
                    'purchase_id' => $purchase->id,
                    'quantity' => $purchase->quantity,
                    'selling_unit_price' => $sellingPrice,
                    'sales_amount' => $totalSales,
                    'cost_amount' => $purchase->total_amount,
                    'profit' => $profit,
                    'sold_by' => auth()->id() ?? $purchase->created_by,
                    'company_id' => $purchase->shift->company_id,
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Payment recorded and sale processed successfully',
                'data' => [
                    'purchase_id' => $purchase->id,
                    'mpesa_code' => $mpesaCode,
                    'is_sold' => $purchase->is_sold
                ]
            ]);
        });

    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Error: ' . $e->getMessage()
        ], 500);
    }
}



public function captureFloatRequestMpesaMessage(Request $request)
{
    $request->validate([
        'float_request_id' => 'required|exists:float_requests,id',
        'message' => 'required|string'
    ]);

    // Use explicit namespace to avoid errors
    $floatRequest = \App\Models\FloatRequest::findOrFail($request->float_request_id);
    $message = $request->message;

    // 1. Validation: Ensure Float Request is APPROVED before payment
    if ($floatRequest->status !== 'approved') {
        return response()->json([
            'success' => false,
            'message' => 'Approval required before payment.'
        ], 422);
    }

    // 2. Validation: Check if already paid
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
        // 3. Parse SMS Logic
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

        // 4. Validation: Amount Matching
        // Ensure the parsed amount matches the requested amount
        if (floatval($amountValue) !== floatval($floatRequest->amount)) {
            return response()->json([
                'success' => false,
                'message' => 'Amount does not match the payment',
                'expected' => $floatRequest->amount,
                'received' => $amountValue
            ], 422);
        }

        // 5. Validation: Prevent duplicate Mpesa code
        if (\App\Models\MpesaTransaction::where('mpesa_receipt_number', $mpesaCode)->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'MPESA transaction already recorded'
            ]);
        }

        // 6. DB Transaction to ensure data integrity
        return \DB::transaction(function () use ($floatRequest, $mpesaCode, $amountValue, $customerName, $message, $timeValue) {
            
            // Create M-Pesa Transaction
            \App\Models\MpesaTransaction::create([
                'transactionable_type' => \App\Models\FloatRequest::class,
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

            // Update float request to 'funded' (Using quotes to avoid truncation error)
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
        });

    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Error: ' . $e->getMessage()
        ], 500);
    }
}

public function captureExpenseMpesaMessage(Request $request)
{
    $request->validate([
        'expense_id' => 'required|exists:expenses,id',
        'message' => 'required|string'
    ]);

    $expense = \App\Models\Expense::findOrFail($request->expense_id);
    $message = $request->message;

    // 1. Validation: Ensure Expense is APPROVED before payment

    // 2. Validation: Check if expense already has a completed payment
    $existingPayment = $expense->mpesaTransactions()
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
        // 3. Parse SMS Logic
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

        // 4. Validation: Amount Matching
        if (floatval($amountValue) !== floatval($expense->amount)) {
            return response()->json([
                'success' => false, 
                'message' => 'Amount does not match the payment',
                'expected' => $expense->amount,
                'received' => $amountValue
            ], 422);
        }

        // 5. Validation: Prevent duplicate Mpesa code
        if (\App\Models\MpesaTransaction::where('mpesa_receipt_number', $mpesaCode)->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'MPESA transaction already recorded'
            ]);
        }

        // 6. Database Transaction
        return \DB::transaction(function () use ($expense, $mpesaCode, $amountValue, $customerName, $message, $timeValue) {
            
            // Create MpesaTransaction Record
            \App\Models\MpesaTransaction::create([
                'transactionable_type' => \App\Models\Expense::class,
                'transactionable_id' => $expense->id,
                'type' => 'expense',
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

            // NEW: Update mpesa_receipt_number on the Expense model
            $expense->update([
                'mpesa_receipt_number' => $mpesaCode,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Expense payment recorded and receipt updated successfully',
                'data' => [
                    'expense_id' => $expense->id,
                    'mpesa_code' => $mpesaCode,
                    'amount' => $amountValue
                ]
            ]);
        });

    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Error: ' . $e->getMessage()
        ], 500);
    }
}

}