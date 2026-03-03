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
}