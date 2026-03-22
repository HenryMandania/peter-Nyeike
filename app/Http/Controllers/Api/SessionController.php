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
    
        if ($user->hasRole(['admin', 'supervisor'])) {
            // Admin/Supervisor: get all open shifts with user and company
            $currentShifts = Shift::with(['user', 'company'])
                ->where('status', 'open')
                ->get();
        } else {
            // Normal users: only their own shifts with user and company
            $currentShifts = Shift::with(['user', 'company'])
                ->where('user_id', $user->id)
                ->where('status', 'open')
                ->get();
        }
    
        // Map through shifts to calculate totals
        $shiftsData = $currentShifts->map(function ($shift) use ($balanceService) {
            $runningBalance = $balanceService->calculate($shift);
            $totalPurchased = Purchase::where('shift_id', $shift->id)->sum('total_amount');
            $totalTransactionFees = Purchase::where('shift_id', $shift->id)->sum('transaction_fee');
            $totalExpenses = Expense::where('shift_id', $shift->id)->sum('amount');
            $totalFloatReceived = FloatRequest::where('shift_id', $shift->id)
                ->where('status', 'approved')
                ->sum('amount');
    
            return [
                'shift' => $shift,
                'company_name' => $shift->company?->name ?? 'No Company Assigned',
                'running_balance' => $runningBalance,
                'total_purchased' => $totalPurchased,
                'total_transaction_fees' => $totalTransactionFees,
                'total_expenses' => $totalExpenses,
                'total_float_received' => $totalFloatReceived,
            ];
        });
    
        return response()->json([
            'shifts' => $shiftsData,
            'message' => 'Active shifts fetched successfully'
        ]);
    }
    /**
     * Open a new shift.
     */
    public function open(Request $request)
    {
        $user = auth()->user();

        // Validate that a company was selected
        $request->validate([
            'company_id' => 'required|exists:companies,id',
        ]);

        // Check for any open session globally for this user
        $existingSession = Shift::where('user_id', $user->id)
            ->where('status', 'open')
            ->first();

        if ($existingSession) {
            return response()->json(['message' => 'You already have an open session. Please close it first.'], 400);
        }

        // Pull balance from the user's last closed shift (irrespective of company)
        $lastClosing = Shift::where('user_id', $user->id)
            ->where('status', 'closed')
            ->orderBy('closed_at', 'desc')
            ->value('closing_balance') ?? 0;

        $session = Shift::create([
            'user_id'         => $user->id,
            'company_id'      => $request->company_id,
            'opening_balance' => $lastClosing,
            'system_balance'  => $lastClosing,
            'status'          => 'open',
            'opened_at'       => now(),
            'created_by'      => $user->id,
        ]);

        return response()->json($session);
    }

    /**
     * Close the active shift.
     * Includes validation to prevent closing if cash is less than system balance.
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
        $closingBalance = (float) $validated['closing_balance'];

        // --- Shortage Validation ---
        if ($closingBalance < $systemBalance) {
            return response()->json([
                'message' => 'Shift closure denied. The closing balance cannot be less than the system balance (Shortage detected).',
                'system_balance' => $systemBalance,
                'provided_balance' => $closingBalance,
                'difference' => $systemBalance - $closingBalance
            ], 422);
        }

        $shift->update([
            'status'          => 'closed',
            'closed_at'       => now(),
            'system_balance'  => $systemBalance,
            'closing_balance' => $closingBalance,
            'cash_difference' => $closingBalance - $systemBalance,
            'closing_notes'   => $validated['closing_notes'],
        ]);

        return response()->json([
            'message' => 'Shift closed successfully',
            'data'    => $shift
        ]);
    }
}