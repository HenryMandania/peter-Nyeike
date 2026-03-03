<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Purchase;
use App\Models\Shift;
use App\Services\BalanceService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Exception;

class PurchaseController extends Controller
{
    public function store(Request $request, BalanceService $balanceService)
    {
        $user = Auth::user();

        // 1. Validation
        $validated = $request->validate([
            'vendor_id'       => 'required|exists:vendors,id',
            'item_id'         => 'required|exists:items,id',
            'quantity'        => 'required|numeric|min:0.01',
            'unit_price'      => 'required|numeric|min:0',
            'payment_method'  => 'required|in:Cash,Mpesa,Bank',
            'transaction_fee' => 'nullable|numeric|min:0',
        ]);

        try {
            // 2. Start Transaction & Lock Shift for Calculation
            return DB::transaction(function () use ($validated, $user, $balanceService) {
                
                // Find active shift and lock it for reading so no other request 
                // changes the balance while we are calculating.
                $activeShift = Shift::where('user_id', $user->id)
                    ->where('status', 'open')
                    ->lockForUpdate() 
                    ->first();

                if (!$activeShift) {
                    return response()->json(['message' => 'No active shift found.'], 403);
                }

                // 3. Precise Calculation
                $fee = $validated['payment_method'] === 'Cash' ? 0 : ($validated['transaction_fee'] ?? 0);
                $purchaseAmount = ($validated['quantity'] * $validated['unit_price']) + $fee;
                
                $availableBalance = $balanceService->calculate($activeShift);
                $variance = $availableBalance - $purchaseAmount;

                // 4. The Golden Rule: Prevent Over-purchase
                if ($variance < 0) {
                    return response()->json([
                        'message' => 'Insufficient funds. Purchase denied.',
                        'details' => [
                            'Available balance' => number_format($availableBalance, 2),
                            'purchase Amount'   => number_format($purchaseAmount, 2),
                            'Variance'          => number_format($variance, 2),
                        ]
                    ], 422);
                }

                // 5. Atomic Creation
                $purchase = Purchase::create([
                    'shift_id'        => $activeShift->id,
                    'vendor_id'       => $validated['vendor_id'],
                    'item_id'         => $validated['item_id'],
                    'quantity'        => $validated['quantity'],
                    'unit_price'      => $validated['unit_price'],
                    'payment_method'  => $validated['payment_method'],
                    'transaction_fee' => $fee,
                    'total_amount'    => $purchaseAmount, // Hard-set to match our check
                    'created_by'      => $user->id,
                ]);

                // 6. Return Success Message
                return response()->json([
                    'message'           => 'There is enough to make this Purchase',
                    'available_balance' => number_format($availableBalance, 2),
                    'purchase_amount'   => number_format($purchaseAmount, 2),
                    'variance'          => number_format($variance, 2),
                    'data'              => $purchase->load(['vendor', 'item']),
                ], 201);
            });

        } catch (Exception $e) {
            return response()->json([
                'message' => 'Transaction failed due to a system error.',
                'error'   => $e->getMessage()
            ], 500);
        }
    }
}