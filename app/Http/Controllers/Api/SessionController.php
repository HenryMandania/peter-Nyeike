<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Shift;
use App\Models\FloatRequest;
use App\Services\BalanceService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SessionController extends Controller
{
    /**
     * Get status with optimized database sums and reliable company names.
     */
    public function status(Request $request, BalanceService $balanceService)
    {
        try {
            $user = auth()->user();
            if (!$user) return response()->json(['message' => 'Unauthenticated'], 401);
        
            $isAdminOrSupervisor = method_exists($user, 'hasAnyRole') 
                ? $user->hasAnyRole(['admin', 'supervisor']) 
                : false;
        
            // 1. Get the IDs of the latest shift for every user
            // This ensures we capture the 10k/20k from closed agents and 10k from open agents
            $latestShiftIds = Shift::select(DB::raw('MAX(id) as id'))
                ->groupBy('user_id')
                ->pluck('id');
        
            // 2. Fetch these specific shifts with sums
            $query = Shift::with(['user', 'company'])
                ->whereIn('id', $latestShiftIds)
                ->withSum('purchases as total_purchased', 'total_amount')
                ->withSum('purchases as total_fees', 'transaction_fee')
                ->withSum('expenses as total_expenses', 'amount')
                ->withSum(['floatRequests as total_float' => function ($q) {
                    $q->where('status', 'approved');
                }], 'amount');
        
            // If not admin, we only care about the user's specific latest state for the main query
            if (!$isAdminOrSupervisor) {
                $query->where('user_id', $user->id);
            }
        
            $relevantShifts = $query->get();
        
            // 3. Calculate the Grand Running Balance (The "40k" Logic)
            // Summing: (Active Shifts Calculated) + (Closed Shifts Final Balances)
            $grandRunningBalance = (float) $relevantShifts->sum(function($shift) use ($balanceService) {
                if ($shift->status === 'open') {
                    return $balanceService->calculate($shift);
                }
                return $shift->closing_balance ?? 0;
            });
        
            // 4. Extract standard aggregates (Only for open shifts to keep totals clean)
            $openShifts = $relevantShifts->where('status', 'open');
            $grandTotalPurchased = (float) $openShifts->sum('total_purchased');
            $grandTotalFees      = (float) $openShifts->sum('total_fees');
            $grandTotalExpenses  = (float) $openShifts->sum('total_expenses');
            $grandTotalFloat     = (float) $openShifts->sum('total_float');
        
            // 5. Personal Shift Logic
            $myLatestShift = $relevantShifts->firstWhere('user_id', $user->id);
            $personalShiftData = null;
        
            if ($myLatestShift && $myLatestShift->status === 'open') {
                $personalShiftData = [
                    'shift_details'   => $myLatestShift,
                    'running_balance' => (float)$balanceService->calculate($myLatestShift),
                    'company_name'    => $myLatestShift->company?->name ?? 'Internal',
                ];
            }
        
            return response()->json([
                'message' => $isAdminOrSupervisor ? 'Global summary fetched' : 'Your summary fetched',
                'data' => [
                    'is_admin_view'          => (bool)$isAdminOrSupervisor,
                    'active_shifts_count'    => $openShifts->count(),
                    'global_running_balance' => $grandRunningBalance,  
                    'total_purchased'        => $grandTotalPurchased,
                    'total_transaction_fees' => $grandTotalFees,
                    'total_expenses'         => $grandTotalExpenses,
                    'total_float_received'   => $grandTotalFloat,
                    'personal_shift'         => $personalShiftData,
                    'company_name'           => $this->resolveHeaderCompanyName($isAdminOrSupervisor, $myLatestShift),
                    'timestamp'              => now()->toIso8601String(),
                ]
            ]);
        
        } catch (\Exception $e) {
            Log::error('Session status error: ' . $e->getMessage());
            return response()->json(['message' => 'Server Error', 'error' => $e->getMessage()], 500);
        }
    }

    private function resolveHeaderCompanyName($isAdmin, $shift) {
        if (!$isAdmin && $shift) {
            return $shift->company?->name ?? 'Company Unavailable';
        }
        return 'All Companies';
    }

    /**
     * Open a new shift (Includes your balance carry-forward logic)
     */
    public function open(Request $request)
    {
        $user = auth()->user();
        $request->validate(['company_id' => 'required|exists:companies,id']);

        if (Shift::where('user_id', $user->id)->where('status', 'open')->exists()) {
            return response()->json(['message' => 'You already have an open session.'], 400);
        }

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
     * Close Shift (Includes your Shortage Validation)
     */
    public function close(Request $request, BalanceService $balanceService)
    {
        $user = auth()->user();

        return DB::transaction(function () use ($user, $request, $balanceService) {
            $shift = Shift::where('user_id', $user->id)
                ->where('status', 'open')
                ->lockForUpdate() 
                ->first();

            if (!$shift) return response()->json(['message' => 'No active shift found.'], 404);

            $validated = $request->validate(['closing_balance' => 'required|numeric|min:0']);
            $systemBalance = (float)$balanceService->calculate($shift);
            $closingBalance = (float)$validated['closing_balance'];

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

            return response()->json(['message' => 'Shift closed successfully', 'data' => $shift]);
        });
    }
}