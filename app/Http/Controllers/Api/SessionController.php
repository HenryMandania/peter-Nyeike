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
    $isAdminOrSupervisor = $user->hasRole(['admin', 'supervisor']);

    // 1. Fetch relevant open shifts (Admin sees all, User sees self)
    $query = Shift::with(['user', 'company'])->where('status', 'open');

    if (!$isAdminOrSupervisor) {
        $query->where('user_id', $user->id);
    }

    $openShifts = $query->get();

    if ($openShifts->isEmpty()) {
        return response()->json([
            'message' => 'No active shifts found.',
            'data' => null
        ], 200);
    }

    // 2. Variables for Aggregation
    $grandRunningBalance = 0;
    $grandTotalPurchased = 0;
    $grandTotalFees = 0;
    $grandTotalExpenses = 0;
    $grandTotalFloat = 0;

    // 3. Process Aggregations
    foreach ($openShifts as $shift) {
        $grandRunningBalance += $balanceService->calculate($shift);
        $grandTotalPurchased += Purchase::where('shift_id', $shift->id)->sum('total_amount');
        $grandTotalFees      += Purchase::where('shift_id', $shift->id)->sum('transaction_fee');
        $grandTotalExpenses  += Expense::where('shift_id', $shift->id)->sum('amount');
        $grandTotalFloat     += FloatRequest::where('shift_id', $shift->id)
                                    ->where('status', 'approved')
                                    ->sum('amount');
    }

    // 4. Identify if the current Admin/Supervisor has a personal active shift
    $myPersonalShift = $openShifts->firstWhere('user_id', $user->id);
    $personalShiftData = null;

    if ($myPersonalShift) {
        $personalShiftData = [
            'shift_details'   => $myPersonalShift,
            'running_balance' => $balanceService->calculate($myPersonalShift),
            'company_name'    => $myPersonalShift->company?->name ?? 'Internal',
        ];
    }

    // 5. Build the Response
    return response()->json([
        'message' => $isAdminOrSupervisor ? 'Global active summary fetched' : 'Your active shift summary fetched',
        'data' => [
            'is_admin_view'          => $isAdminOrSupervisor,
            'active_shifts_count'    => $openShifts->count(),
            'global_running_balance' => $grandRunningBalance,
            'total_purchased'        => $grandTotalPurchased,
            'total_transaction_fees' => $grandTotalFees,
            'total_expenses'         => $grandTotalExpenses,
            'total_float_received'   => $grandTotalFloat,
            
            // If Admin has a shift, it shows here. If User, it shows their only shift.
            'personal_shift'         => $personalShiftData,
            
            // For convenience in UI
            'company_name'           => !$isAdminOrSupervisor && $myPersonalShift 
                                        ? $myPersonalShift->company?->name 
                                        : 'All Companies',
        ]
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

    // 1. Automatically find the ONLY open shift for this user
    $shift = Shift::where('user_id', $user->id)
        ->where('status', 'open')
        ->lockForUpdate() // Prevents duplicate closing if two requests hit at once
        ->first();

    if (!$shift) {
        return response()->json([
            'message' => 'No active shift found to close.'
        ], 404);
    }

    $validated = $request->validate([
        'closing_balance' => 'required|numeric|min:0',
         
    ]);

    // 2. Calculate final system balance using your service
    $systemBalance = $balanceService->calculate($shift);
    $closingBalance = (float) $validated['closing_balance'];

    // 3. Shortage Validation (Business Rule: Cannot close with less cash than system expects)
    if ($closingBalance < $systemBalance) {
        return response()->json([
            'message' => 'Shift closure denied. Shortage detected.',
            'system_balance'   => $systemBalance,
            'provided_balance' => $closingBalance,
            'difference'       => $systemBalance - $closingBalance
        ], 422);
    }

    // 4. Perform the Closure
    $shift->update([
        'status'          => 'closed',
        'closed_at'       => now(),
        'system_balance'  => $systemBalance,
        'closing_balance' => $closingBalance,
        'cash_difference' => $closingBalance - $systemBalance,
         
    ]);

    return response()->json([
        'message' => 'Shift closed successfully',
        'data'    => $shift
    ]);
}
}