<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Shift;
use App\Models\FloatRequest;
use App\Services\BalanceService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SessionController extends Controller
{
    /**
     * Get the status of the current user's shift including all running totals.
     * Includes a forced reload of the company relation to ensure the name updates.
     */
    public function status(Request $request, BalanceService $balanceService)
    {
        try {
            $user = auth()->user();
            
            if (!$user) {
                return response()->json(['message' => 'Unauthenticated', 'data' => null], 401);
            }
            
            // Safe role check for admin/supervisor views
            $isAdminOrSupervisor = method_exists($user, 'hasAnyRole') 
                ? $user->hasAnyRole(['admin', 'supervisor']) 
                : false;

            // 1. Fetch open shifts with calculated sums from the database
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

            // Handle no active shifts gracefully
            if ($openShifts->isEmpty()) {
                return response()->json([
                    'message' => 'No active shifts found.',
                    'data' => [
                        'is_admin_view' => (bool)$isAdminOrSupervisor,
                        'active_shifts_count' => 0,
                        'global_running_balance' => 0.0,
                        'total_purchased' => 0.0,
                        'total_transaction_fees' => 0.0,
                        'total_expenses' => 0.0,
                        'total_float_received' => 0.0,
                        'personal_shift' => null,
                        'company_name' => !$isAdminOrSupervisor ? 'No Active Shift' : 'All Companies',
                    ]
                ], 200);
            }

            // 2. Aggregate running totals
            $grandTotalPurchased = (float) $openShifts->sum('total_purchased');
            $grandTotalFees      = (float) $openShifts->sum('total_fees');
            $grandTotalExpenses  = (float) $openShifts->sum('total_expenses');
            $grandTotalFloat     = (float) $openShifts->sum('total_float');
            
            $grandRunningBalance = (float) $openShifts->sum(function($shift) use ($balanceService) {
                try {
                    return $balanceService->calculate($shift);
                } catch (\Exception $e) {
                    Log::error("Balance calculation error for shift {$shift->id}: " . $e->getMessage());
                    return 0.0;
                }
            });

            // 3. Prepare personal shift data
            $myPersonalShift = $openShifts->firstWhere('user_id', $user->id);
            $personalShiftData = null;

            if ($myPersonalShift) {
                // IMPORTANT: Force reload company to ensure name changes reflect immediately
                $myPersonalShift->load('company');

                $personalShiftData = [
                    'shift_details' => [
                        'id' => $myPersonalShift->id,
                        'opening_balance' => (float)$myPersonalShift->opening_balance,
                        'system_balance' => (float)$myPersonalShift->system_balance,
                        'status' => $myPersonalShift->status,
                        'opened_at' => $myPersonalShift->opened_at,
                        'company_id' => $myPersonalShift->company_id,
                    ],
                    'running_balance' => (float)$balanceService->calculate($myPersonalShift),
                    'company_name' => $myPersonalShift->company?->name ?? 'Internal/Default',
                ];
            }

            return response()->json([
                'message' => $isAdminOrSupervisor ? 'Global summary fetched' : 'Your shift summary fetched',
                'data' => [
                    'is_admin_view'          => (bool)$isAdminOrSupervisor,
                    'active_shifts_count'    => $openShifts->count(),
                    'global_running_balance' => $grandRunningBalance,
                    'total_purchased'        => $grandTotalPurchased,
                    'total_transaction_fees' => $grandTotalFees,
                    'total_expenses'         => $grandTotalExpenses,
                    'total_float_received'   => $grandTotalFloat,
                    'personal_shift'         => $personalShiftData,
                    'company_name'           => $this->getCompanyName($isAdminOrSupervisor, $myPersonalShift),
                    'timestamp'              => now()->toIso8601String(),
                ]
            ]);
            
        } catch (\Exception $e) {
            Log::error('Session status error: ' . $e->getMessage());
            return response()->json([
                'message' => 'Server Error', 
                'error' => config('app.debug') ? $e->getMessage() : 'Internal Server Error'
            ], 500);
        }
    }

    /**
     * Helper to determine which company name to show in the header
     */
    private function getCompanyName($isAdmin, $shift) {
        if (!$isAdmin && $shift) {
            return $shift->company?->name ?? 'Company Unavailable';
        }
        return 'All Companies';
    }

    /**
     * Open a new shift
     */
    public function open(Request $request)
    {
        try {
            $user = auth()->user();
            $request->validate(['company_id' => 'required|exists:companies,id']);

            if (Shift::where('user_id', $user->id)->where('status', 'open')->exists()) {
                return response()->json(['message' => 'You already have an open shift.'], 400);
            }

            if (FloatRequest::where('user_id', $user->id)->where('status', 'pending')->exists()) {
                return response()->json(['message' => 'Pending float request must be resolved first.'], 403);
            }

            $lastClosing = Shift::where('user_id', $user->id)
                ->where('status', 'closed')
                ->orderBy('closed_at', 'desc')
                ->value('closing_balance') ?? 0;

            $session = Shift::create([
                'user_id' => $user->id,
                'company_id' => $request->company_id,
                'opening_balance' => $lastClosing,
                'system_balance' => $lastClosing,
                'status' => 'open',
                'opened_at' => now(),
                'created_by' => $user->id,
            ]);

            return response()->json(['message' => 'Shift opened successfully', 'data' => $session], 201);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Failed to open shift', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Close the active shift
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
                    'message' => 'Shortage detected. Closure denied.',
                    'system_balance' => $systemBalance,
                    'provided_balance' => $closingBalance,
                    'difference' => $systemBalance - $closingBalance
                ], 422);
            }

            $shift->update([
                'status' => 'closed',
                'closed_at' => now(),
                'system_balance' => $systemBalance,
                'closing_balance' => $closingBalance,
                'cash_difference' => $closingBalance - $systemBalance,
            ]);

            return response()->json(['message' => 'Shift closed successfully', 'data' => $shift]);
        });
    }
}