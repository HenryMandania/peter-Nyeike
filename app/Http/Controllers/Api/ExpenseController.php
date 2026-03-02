<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Expense;
use App\Models\Shift;
use App\Services\BalanceService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ExpenseController extends Controller
{
    /**
     * Store a new shift expense.
     */
    public function store(Request $request, BalanceService $balanceService)
    {
        $user = Auth::user();

        // 1. Validate Input
        $validated = $request->validate([
            'expense_category_id' => 'required|exists:expense_categories,id',
            'amount' => 'required|numeric|min:0.01',
            'description' => 'nullable|string|max:500',
        ]);

        // 2. Find Active Shift
        $activeShift = Shift::where('user_id', $user->id)
            ->where('status', 'open')
            ->first();

        if (!$activeShift) {
            return response()->json([
                'message' => 'Action denied. You do not have an active shift.'
            ], 403);
        }

        // 3. Check Balance (Logic from your Filament Resource)
        $currentBalance = $balanceService->calculate($activeShift);
        
        if (floatval($validated['amount']) > $currentBalance) {
            return response()->json([
                'message' => "Insufficient funds! Your shift balance is KES " . number_format($currentBalance, 2)
            ], 422);
        }

        // 4. Create Expense
        $expense = Expense::create([
            'shift_id'           => $activeShift->id,
            'expense_category_id' => $validated['expense_category_id'],
            'amount'             => $validated['amount'],
            'description'        => $validated['description'],
            'created_by'         => $user->id,
        ]);

        return response()->json([
            'message' => 'Expense recorded successfully.',
            'data' => $expense->load('category'),
            'remaining_balance' => $balanceService->calculate($activeShift)
        ], 201);
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