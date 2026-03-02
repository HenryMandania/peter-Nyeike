<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Shift;
use App\Models\Purchase;
use App\Models\Expense;
use App\Models\FloatRequest;
use App\Services\BalanceService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SessionController extends Controller
{
    /**
     * Get the status of the current user's shift including all running totals.
     */
    public function status(Request $request, BalanceService $balanceService)
    {
        $user = auth()->user();

        $currentShift = Shift::where('user_id', $user->id)
            ->where('status', 'open')
            ->first();

        $lastShift = Shift::where('user_id', $user->id)
            ->where('status', 'closed')
            ->orderBy('closed_at', 'desc')
            ->first();

        // Initialize variables
        $runningBalance = 0;
        $totalPurchased = 0;
        $totalTransactionFees = 0; 
        $totalExpenses = 0;
        $totalFloatReceived = 0;

        if ($currentShift) {
            // 1. Running Balance (Cash on hand)
            $runningBalance = $balanceService->calculate($currentShift);

            // 2. Sum of Purchase amounts
            $totalPurchased = Purchase::where('shift_id', $currentShift->id)->sum('total_amount');

            // 3. Sum of Transaction Fees (Using your correct column name)
            $totalTransactionFees = Purchase::where('shift_id', $currentShift->id)->sum('transaction_fee');

            // 4. Sum of Expenses
            $totalExpenses = Expense::where('shift_id', $currentShift->id)->sum('amount');

            // 5. Sum of Approved Floats
            $totalFloatReceived = FloatRequest::where('shift_id', $currentShift->id)
                ->where('status', 'approved')
                ->sum('amount');
        }

        return response()->json([
            'is_shift_open' => (bool) $currentShift,
            'current_shift' => $currentShift,
            'running_balance' => $runningBalance, 
            'total_purchased' => $totalPurchased,
            'total_transaction_fees' => $totalTransactionFees,
            'total_expenses' => $totalExpenses,
            'total_float_received' => $totalFloatReceived,
            'suggested_opening_balance' => $lastShift ? $lastShift->closing_balance : 0,
        ]);
    }

    /**
     * Open a new shift.
     */
    public function open(Request $request)
    {
        $user = auth()->user();

        $existingSession = Shift::where('user_id', $user->id)
            ->where('status', 'open')
            ->first();

        if ($existingSession) {
            return response()->json(['message' => 'You already have an open session.'], 400);
        }

        $lastClosing = Shift::where('user_id', $user->id)
            ->where('status', 'closed')
            ->orderBy('closed_at', 'desc')
            ->value('closing_balance') ?? 0;

        $session = Shift::create([
            'user_id' => $user->id,
            'opening_balance' => $lastClosing,
            'system_balance' => $lastClosing,
            'status' => 'open',
            'opened_at' => now(),
            'created_by' => $user->id,
        ]);

        return response()->json($session);
    }

    /**
     * Close the active shift.
     */
    public function close(Request $request, BalanceService $balanceService)
    {
        $user = auth()->user();

        $shift = Shift::where('user_id', $user->id)
            ->where('status', 'open')
            ->first();

        if (!$shift) {
            return response()->json(['message' => 'No active shift found.'], 404);
        }

        $validated = $request->validate([
            'closing_balance' => 'required|numeric|min:0',
            'closing_notes'   => 'nullable|string',
        ]);

        $systemBalance = $balanceService->calculate($shift);

        $shift->update([
            'status'          => 'closed',
            'closed_at'       => now(),
            'system_balance'  => $systemBalance,
            'closing_balance' => $validated['closing_balance'],
            'cash_difference' => $validated['closing_balance'] - $systemBalance,
            'closing_notes'   => $validated['closing_notes'],
        ]);

        return response()->json([
            'message' => 'Shift closed successfully',
            'data'    => $shift
        ]);
    }
}