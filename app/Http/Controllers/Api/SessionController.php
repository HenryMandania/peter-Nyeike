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
use Illuminate\Support\Facades\DB;

class SessionController extends Controller
{
    /**
     * Get the status of the current user's shift including all running totals.
     * Optimized using withSum to prevent N+1 query overhead.
     */
    public function status(Request $request, BalanceService $balanceService)
    {
        $user = auth()->user();
        $isAdminOrSupervisor = $user->hasAnyRole(['admin', 'supervisor']);

        // 1. Fetch shifts with sub-totals pre-calculated by the database
        $query = Shift::with(['user', 'company'])
            ->where('status', 'open')
            ->withSum('purchases as total_purchased', 'total_amount')
            ->withSum('purchases as total_fees', 'transaction_fee')
            ->withSum('expenses as total_expenses', 'amount')
            ->withSum(['floatRequests as total_float' => function ($query) {
                $query->where('status', 'approved');
            }], 'amount');

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

        // 2. Aggregate the pre-calculated sums across all fetched shifts
        $grandRunningBalance = $openShifts->sum(fn($shift) => $balanceService->calculate($shift));
        $grandTotalPurchased = $openShifts->sum('total_purchased') ?? 0;
        $grandTotalFees      = $openShifts->sum('total_fees') ?? 0;
        $grandTotalExpenses  = $openShifts->sum('total_expenses') ?? 0;
        $grandTotalFloat     = $openShifts->sum('total_float') ?? 0;

        // 3. Identify if the current user has a personal active shift
        $myPersonalShift = $openShifts->firstWhere('user_id', $user->id);
        $personalShiftData = null;

        if ($myPersonalShift) {
            $personalShiftData = [
                'shift_details'   => $myPersonalShift,
                'running_balance' => $balanceService->calculate($myPersonalShift),
                'company_name'    => $myPersonalShift->company?->name ?? 'Internal',
            ];
        }

        // 4. Build the Response
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
                'personal_shift'         => $personalShiftData,
                'company_name'           => !$isAdminOrSupervisor && $myPersonalShift 
                                            ? $myPersonalShift->company?->name 
                                            : 'All Companies',
            ]
        ]);
    }

    /**
     * Open a new shift.
     * Includes a safety check for pending float requests.
     */
    public function open(Request $request)
    {
        $user = auth()->user();

        $request->validate([
            'company_id' => 'required|exists:companies,id',
        ]);

        // Guard 1: Prevent multiple open shifts
        $existingSession = Shift::where('user_id', $user->id)
            ->where('status', 'open')
            ->first();

        if ($existingSession) {
            return response()->json(['message' => 'You already have an open session.'], 400);
        }

        // Guard 2: Safety Check - Prevent opening if there's an unhandled float request
        $pendingFloat = FloatRequest::where('user_id', $user->id)
            ->where('status', 'pending')
            ->exists();

        if ($pendingFloat) {
            return response()->json([
                'message' => 'Cannot open shift. You have a pending float request that must be approved or rejected first.'
            ], 403);
        }

        // Pull balance from the user's last closed shift
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
     * Uses database locking to prevent race conditions during closure.
     */
    public function close(Request $request, BalanceService $balanceService)
    {
        $user = auth()->user();

        return DB::transaction(function () use ($user, $request, $balanceService) {
            $shift = Shift::where('user_id', $user->id)
                ->where('status', 'open')
                ->lockForUpdate() 
                ->first();

            if (!$shift) {
                return response()->json(['message' => 'No active shift found.'], 404);
            }

            $validated = $request->validate([
                'closing_balance' => 'required|numeric|min:0',
            ]);

            $systemBalance = $balanceService->calculate($shift);
            $closingBalance = (float) $validated['closing_balance'];

            // Shortage Validation
            if ($closingBalance < $systemBalance) {
                return response()->json([
                    'message' => 'Shift closure denied. Shortage detected.',
                    'system_balance'   => $systemBalance,
                    'provided_balance' => $closingBalance,
                    'difference'       => $systemBalance - $closingBalance
                ], 422);
            }

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
        });
    }
}