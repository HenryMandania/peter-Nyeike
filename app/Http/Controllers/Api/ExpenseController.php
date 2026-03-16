<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Expense;
use App\Models\Shift;
use App\Services\BalanceService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Exception;

class ExpenseController extends Controller
{
    /**
     * Store a new shift expense with balance protection.
     */
    public function store(Request $request, BalanceService $balanceService)
    {
        $user = Auth::user();

        // 1. Validate Input
        $validated = $request->validate([
            'expense_category_id' => 'required|exists:expense_categories,id',
            'amount'              => 'required|numeric|min:0.01',
            'description'         => 'nullable|string|max:500',
        ]);

        try {
            // 2. Start Transaction & Lock Shift for Calculation
            return DB::transaction(function () use ($validated, $user, $balanceService) {
                
                // We lock the shift record so no other transaction can calculate 
                // the balance until this one is finished.
                $activeShift = Shift::where('user_id', $user->id)
                    ->where('status', 'open')
                    ->lockForUpdate()
                    ->first();

                if (!$activeShift) {
                    return response()->json([
                        'message' => 'Action denied. You do not have an active shift.'
                    ], 403);
                }

                // 3. Precise Balance Calculation
                $expenseAmount = (float) $validated['amount'];
                $availableBalance = $balanceService->calculate($activeShift);
                $variance = $availableBalance - $expenseAmount;

                // 4. Verification Check
                if ($variance < 0) {
                    return response()->json([
                        'message'           => 'Insufficient funds to record this expense.',
                        'available_balance' => number_format($availableBalance, 2),
                        'purchase_amount'   => number_format($expenseAmount, 2), // Keep naming consistent for frontend
                        'variance'          => number_format($variance, 2),
                    ], 422);
                }

                // 5. Atomic Creation
                $expense = Expense::create([
                    'shift_id'            => $activeShift->id,
                    'expense_category_id' => $validated['expense_category_id'],
                    'amount'              => $expenseAmount,
                    'description'         => $validated['description'],
                    'created_by'          => $user->id,
                ]);

                // 6. Return Informative Success Message
                return response()->json([
                    'message'           => 'There is enough to make this Purchase', // Using requested phrase
                    'available_balance' => number_format($availableBalance, 2),
                    'purchase_amount'   => number_format($expenseAmount, 2),
                    'variance'          => number_format($variance, 2),
                    'data'              => $expense->load('category'),
                ], 201);
            });

        } catch (Exception $e) {
            return response()->json([
                'message' => 'Failed to record expense due to a server error.',
                'error'   => $e->getMessage()
            ], 500);
        }
    }

    /**
     * List expenses for the current user's active shift.
     */
    public function index()
    {
        $expenses = Expense::where('created_by', Auth::id())
            ->with('category:id,name')
            ->latest()
            ->paginate(15);

        return response()->json($expenses);
    }

    public function captureExpenseMpesaMessage(Request $request)
{
    $request->validate([
        'expense_id' => 'required|exists:expenses,id',
        'message' => 'required|string'
    ]);

    $expense = \App\Models\Expense::findOrFail($request->expense_id);
    $message = $request->message;

    // Check if expense already has a completed payment
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
        if (\App\Models\MpesaTransaction::where('mpesa_receipt_number', $mpesaCode)->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'MPESA transaction already recorded'
            ]);
        }

        // Create MpesaTransaction
        $transaction = \App\Models\MpesaTransaction::create([
            'transactionable_type' => Expense::class,
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

        // Optionally, mark expense as paid
        $expense->update(['status' => 'paid']);

        return response()->json([
            'success' => true,
            'message' => 'Expense payment recorded successfully',
            'data' => [
                'expense_id' => $expense->id,
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