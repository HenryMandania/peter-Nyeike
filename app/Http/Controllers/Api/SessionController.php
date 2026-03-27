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
use Illuminate\Database\Eloquent\ModelNotFoundException;

class SessionController extends Controller
{
    /**
     * Get the status of the current user's shift including all running totals.
     * Optimized using withSum to prevent N+1 query overhead.
     */
    public function status(Request $request, BalanceService $balanceService)
    {
        try {
            $user = auth()->user();
            
            // Guard against unauthenticated users
            if (!$user) {
                return response()->json([
                    'message' => 'Unauthenticated',
                    'data' => null
                ], 401);
            }
            
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

            // Handle no active shifts gracefully
            if ($openShifts->isEmpty()) {
                return response()->json([
                    'message' => 'No active shifts found.',
                    'data' => [
                        'is_admin_view' => $isAdminOrSupervisor,
                        'active_shifts_count' => 0,
                        'global_running_balance' => 0,
                        'total_purchased' => 0,
                        'total_transaction_fees' => 0,
                        'total_expenses' => 0,
                        'total_float_received' => 0,
                        'personal_shift' => null,
                        'company_name' => !$isAdminOrSupervisor ? 'No Active Shift' : 'All Companies',
                    ]
                ], 200);
            }

            // 2. Aggregate the pre-calculated sums across all fetched shifts
            $grandRunningBalance = $openShifts->sum(function($shift) use ($balanceService) {
                try {
                    return $balanceService->calculate($shift);
                } catch (\Exception $e) {
                    \Log::error('Balance calculation error for shift ' . $shift->id . ': ' . $e->getMessage());
                    return 0;
                }
            });
            
            $grandTotalPurchased = $openShifts->sum('total_purchased') ?? 0;
            $grandTotalFees      = $openShifts->sum('total_fees') ?? 0;
            $grandTotalExpenses  = $openShifts->sum('total_expenses') ?? 0;
            $grandTotalFloat     = $openShifts->sum('total_float') ?? 0;

            // 3. Identify if the current user has a personal active shift
            $myPersonalShift = $openShifts->firstWhere('user_id', $user->id);
            $personalShiftData = null;

            if ($myPersonalShift) {
                try {
                    $personalShiftData = [
                        'shift_details' => [
                            'id' => $myPersonalShift->id,
                            'opening_balance' => $myPersonalShift->opening_balance,
                            'system_balance' => $myPersonalShift->system_balance,
                            'status' => $myPersonalShift->status,
                            'opened_at' => $myPersonalShift->opened_at,
                            'company_id' => $myPersonalShift->company_id,
                        ],
                        'running_balance' => $balanceService->calculate($myPersonalShift),
                        'company_name' => $myPersonalShift->company?->name ?? 'Unknown Company',
                        'company_exists' => !is_null($myPersonalShift->company),
                    ];
                } catch (\Exception $e) {
                    \Log::error('Error building personal shift data: ' . $e->getMessage());
                    $personalShiftData = [
                        'shift_details' => null,
                        'running_balance' => 0,
                        'company_name' => 'Error Loading Data',
                        'company_exists' => false,
                        'error' => 'Unable to load shift details'
                    ];
                }
            }

            // 4. Build the Response with safe defaults
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
                    'company_name'           => $this->getCompanyName($isAdminOrSupervisor, $myPersonalShift),
                    'app_version'            => config('app.version', '1.0.0'),
                    'timestamp'              => now()->toISOString(),
                ]
            ]);
            
        } catch (\Exception $e) {
            \Log::error('Session status error: ' . $e->getMessage(), [
                'user_id' => auth()->id(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'message' => 'Unable to fetch session status. Please try again.',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error',
                'data' => null
            ], 500);
        }
    }

    /**
     * Safely get company name with fallbacks
     */
    private function getCompanyName($isAdminOrSupervisor, $myPersonalShift)
    {
        try {
            if (!$isAdminOrSupervisor && $myPersonalShift) {
                return $myPersonalShift->company?->name ?? 'Company Unavailable';
            }
            return 'All Companies';
        } catch (\Exception $e) {
            return 'Company Information Unavailable';
        }
    }

    /**
     * Open a new shift with enhanced validation.
     */
    public function open(Request $request)
    {
        try {
            $user = auth()->user();
            
            if (!$user) {
                return response()->json(['message' => 'Unauthenticated'], 401);
            }

            $request->validate([
                'company_id' => 'required|exists:companies,id',
            ]);

            // Guard 1: Prevent multiple open shifts
            $existingSession = Shift::where('user_id', $user->id)
                ->where('status', 'open')
                ->first();

            if ($existingSession) {
                return response()->json([
                    'message' => 'You already have an open session.',
                    'shift_id' => $existingSession->id
                ], 400);
            }

            // Guard 2: Verify user has access to this company
            $company = \App\Models\Company::find($request->company_id);
            if (!$company) {
                return response()->json(['message' => 'Invalid company selected.'], 400);
            }

            // Check if user belongs to this company (if your app uses company-user relationships)
            if (!in_array($company->id, $user->companies()->pluck('id')->toArray())) {
                return response()->json(['message' => 'You do not have access to this company.'], 403);
            }

            // Guard 3: Safety Check - Prevent opening if there's an unhandled float request
            $pendingFloat = FloatRequest::where('user_id', $user->id)
                ->where('status', 'pending')
                ->exists();

            if ($pendingFloat) {
                return response()->json([
                    'message' => 'Cannot open shift. You have a pending float request that must be approved or rejected first.',
                    'pending_float_exists' => true
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

            return response()->json([
                'message' => 'Shift opened successfully',
                'data' => $session
            ], 201);
            
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            \Log::error('Shift open error: ' . $e->getMessage(), [
                'user_id' => auth()->id(),
                'company_id' => $request->company_id ?? null
            ]);
            
            return response()->json([
                'message' => 'Unable to open shift. Please try again.',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Close the active shift with enhanced error handling.
     */
    public function close(Request $request, BalanceService $balanceService)
    {
        try {
            $user = auth()->user();
            
            if (!$user) {
                return response()->json(['message' => 'Unauthenticated'], 401);
            }

            return DB::transaction(function () use ($user, $request, $balanceService) {
                $shift = Shift::where('user_id', $user->id)
                    ->where('status', 'open')
                    ->lockForUpdate() 
                    ->first();

                if (!$shift) {
                    return response()->json([
                        'message' => 'No active shift found.',
                        'has_active_shift' => false
                    ], 404);
                }

                $validated = $request->validate([
                    'closing_balance' => 'required|numeric|min:0',
                ]);

                try {
                    $systemBalance = $balanceService->calculate($shift);
                } catch (\Exception $e) {
                    \Log::error('Balance calculation error during shift close: ' . $e->getMessage());
                    return response()->json([
                        'message' => 'Unable to calculate shift balance. Please contact support.',
                        'error' => config('app.debug') ? $e->getMessage() : 'Calculation error'
                    ], 500);
                }
                
                $closingBalance = (float) $validated['closing_balance'];

                // Shortage Validation with more informative response
                if ($closingBalance < $systemBalance) {
                    $difference = $systemBalance - $closingBalance;
                    return response()->json([
                        'message' => 'Shift closure denied. Shortage detected.',
                        'system_balance'   => $systemBalance,
                        'provided_balance' => $closingBalance,
                        'difference'       => $difference,
                        'shortage_amount'  => $difference,
                        'can_proceed'      => false
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
                    'data'    => [
                        'id' => $shift->id,
                        'status' => $shift->status,
                        'closed_at' => $shift->closed_at,
                        'closing_balance' => $shift->closing_balance,
                        'system_balance' => $shift->system_balance,
                        'cash_difference' => $shift->cash_difference
                    ]
                ]);
            });
            
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            \Log::error('Shift close error: ' . $e->getMessage(), [
                'user_id' => auth()->id(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'message' => 'Unable to close shift. Please try again.',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }
}