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

            // 1. Determine Role (Safe check)
            $isAdminOrSupervisor = method_exists($user, 'hasAnyRole') 
                ? $user->hasAnyRole(['admin', 'supervisor']) 
                : false;

            // 2. Optimized Query (Database does the heavy lifting)
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
                    'data' => [
                        'is_admin_view'          => (bool)$isAdminOrSupervisor,
                        'active_shifts_count'    => 0,
                        'global_running_balance' => 0.0,
                        'total_purchased'        => 0.0,
                        'total_transaction_fees' => 0.0,
                        'total_expenses'         => 0.0,
                        'total_float_received'   => 0.0,
                        'personal_shift'         => null,
                        'company_name'           => $isAdminOrSupervisor ? 'All Companies' : 'No Active Shift',
                        'timestamp'              => now()->toIso8601String(),
                    ]
                ], 200);
            }

            // 3. Aggregate calculated sums (Fast - no extra DB hits)
            $grandTotalPurchased = (float) $openShifts->sum('total_purchased');
            $grandTotalFees      = (float) $openShifts->sum('total_fees');
            $grandTotalExpenses  = (float) $openShifts->sum('total_expenses');
            $grandTotalFloat     = (float) $openShifts->sum('total_float');
            
            $grandRunningBalance = (float) $openShifts->sum(function($shift) use ($balanceService) {
                return $balanceService->calculate($shift);
            });

            // 4. Personal Shift Logic (Matches your reference version)
            $myPersonalShift = $openShifts->firstWhere('user_id', $user->id);
            $personalShiftData = null;

            if ($myPersonalShift) {
                $personalShiftData = [
                    'shift_details'   => $myPersonalShift,
                    'running_balance' => (float)$balanceService->calculate($myPersonalShift),
                    'company_name'    => $myPersonalShift->company?->name ?? 'Internal',
                ];
            }

            // 5. Final Response
            return response()->json([
                'message' => $isAdminOrSupervisor ? 'Global active summary fetched' : 'Your active shift summary fetched',
                'data' => [
                    'is_admin_view'          => (bool)$isAdminOrSupervisor,
                    'active_shifts_count'    => $openShifts->count(),
                    'global_running_balance' => $grandRunningBalance,
                    'total_purchased'        => $grandTotalPurchased,
                    'total_transaction_fees' => $grandTotalFees,
                    'total_expenses'         => $grandTotalExpenses,
                    'total_float_received'   => $grandTotalFloat,
                    'personal_shift'         => $personalShiftData,
                    'company_name'           => $this->resolveHeaderCompanyName($isAdminOrSupervisor, $myPersonalShift),
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