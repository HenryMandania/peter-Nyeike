<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Purchase;
use App\Models\Shift;
use App\Services\BalanceService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PurchaseController extends Controller
{
    public function store(Request $request, BalanceService $balanceService)
    {
        $user = Auth::user();

        // 1. Validate Input
        $validated = $request->validate([
            'vendor_id' => 'required|exists:vendors,id',
            'item_id' => 'required|exists:items,id',
            'quantity' => 'required|numeric|min:0.01',
            'unit_price' => 'required|numeric|min:0',
            'payment_method' => 'required|in:Cash,Mpesa,Bank',
            'transaction_fee' => 'nullable|numeric|min:0',
        ]);

        // 2. Check for Active Shift
        $activeShift = Shift::where('user_id', $user->id)
            ->where('status', 'open')
            ->first();

        if (!$activeShift) {
            return response()->json([
                'message' => 'No active shift found. You must open a shift before recording purchases.'
            ], 403);
        }

        // 3. Calculate Cost & Verify Balance
        $fee = $validated['payment_method'] === 'Cash' ? 0 : ($validated['transaction_fee'] ?? 0);
        $totalCost = ($validated['quantity'] * $validated['unit_price']) + $fee;

        $availableBalance = $balanceService->calculate($activeShift);

        if ($totalCost > $availableBalance) {
            return response()->json([
                'message' => 'Insufficient balance.',
                'available' => $availableBalance,
                'required' => $totalCost,
                'shortage' => $totalCost - $availableBalance
            ], 422);
        }

        // 4. Create Purchase
        // Note: total_amount and shift_id are handled by the Model's booted() method
        $purchase = Purchase::create([
            'vendor_id' => $validated['vendor_id'],
            'item_id' => $validated['item_id'],
            'quantity' => $validated['quantity'],
            'unit_price' => $validated['unit_price'],
            'payment_method' => $validated['payment_method'],
            'transaction_fee' => $fee,
        ]);

        return response()->json([
            'message' => 'Purchase recorded successfully',
            'purchase' => $purchase->load(['vendor', 'item']),
            'new_balance' => $balanceService->calculate($activeShift)
        ], 201);
    }

    public function index()
    {
        // Returns purchases for the current user's active shift
        $purchases = Purchase::where('created_by', Auth::id())
            ->with(['vendor', 'item'])
            ->latest()
            ->paginate(10);

        return response()->json($purchases);
    }
}