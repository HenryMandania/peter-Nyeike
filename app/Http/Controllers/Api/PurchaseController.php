<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Purchase;
use App\Models\Shift;
use App\Services\BalanceService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Exception;
use App\Services\MpesaService;
use App\Jobs\MpesaStkPushJob;
 
class PurchaseController extends Controller implements HasMiddleware
{
    /**
     * Define middleware for the controller (Laravel 11+ Style)
     */
    public static function middleware(): array
    {
        return [
            // Ensure the user is authenticated for all actions
            'auth:sanctum',

            // Permission-based restrictions
            new Middleware('permission:purchase.view', only: ['index']),
            new Middleware('permission:purchase.create', only: ['store']),
            new Middleware('permission:purchase.approve', only: ['approve','reject']),
        ];
    }

    /**
     * Fetch all purchases
     */
    public function index(): JsonResponse
    {
        $purchases = Purchase::with(['vendor', 'item', 'shift'])
            ->orderBy('created_at', 'desc')
            ->paginate(15);

        return response()->json([
            'message' => 'Purchases fetched successfully.',
            'data'    => $purchases,
        ], 200);
    }

    /**
     * Create a new purchase
     */
    public function store(Request $request, BalanceService $balanceService): JsonResponse
    {
        $user = Auth::user();

        $validated = $request->validate([
            'vendor_id'       => 'required|exists:vendors,id',
            'item_id'         => 'required|exists:items,id',
            'quantity'        => 'required|numeric|min:0.01',
            'unit_price'      => 'required|numeric|min:0',
            'payment_method'  => 'required|in:Cash,Mpesa,Bank',
            'transaction_fee' => 'nullable|numeric|min:0',
        ]);

        try {
            return DB::transaction(function () use ($validated, $user, $balanceService) {

                // Lock active shift to prevent race conditions during balance check
                $activeShift = Shift::where('user_id', $user->id)
                    ->where('status', 'open')
                    ->lockForUpdate()
                    ->first();

                if (!$activeShift) {
                    return response()->json(['message' => 'No active shift found.'], 403);
                }

                // Calculate total (Fees only apply to non-cash transactions)
                $fee = $validated['payment_method'] === 'Cash' ? 0 : ($validated['transaction_fee'] ?? 0);
                $purchaseAmount = ($validated['quantity'] * $validated['unit_price']) + $fee;

                $availableBalance = $balanceService->calculate($activeShift);
                $variance = $availableBalance - $purchaseAmount;

                if ($variance < 0) {
                    return response()->json([
                        'message' => 'Insufficient funds. Purchase denied.',
                        'details' => [
                            'available_balance' => number_format($availableBalance, 2),
                            'purchase_amount'   => number_format($purchaseAmount, 2),
                            'variance'          => number_format($variance, 2),
                        ]
                    ], 422);
                }

                // Create purchase record
                $purchase = Purchase::create([
                    'shift_id'        => $activeShift->id,
                    'vendor_id'       => $validated['vendor_id'],
                    'item_id'         => $validated['item_id'],
                    'quantity'        => $validated['quantity'],
                    'unit_price'      => $validated['unit_price'],
                    'payment_method'  => $validated['payment_method'],
                    'transaction_fee' => $fee,
                    'total_amount'    => $purchaseAmount,
                    'created_by'      => $user->id,
                    'status'          => 'pending', 
                ]);

                return response()->json([
                    'message'           => 'Purchase created successfully.',
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

    /**
     * Approve a purchase
     */
    public function approve(Purchase $purchase): JsonResponse
    {
        $user = Auth::user();

        // Check if already processed
        if ($purchase->status !== 'pending') {
            return response()->json([
                'message' => "This purchase cannot be approved. Current status: {$purchase->status}"
            ], 422);
        }

        $purchase->update([
            'status' => 'approved',
            'approved_by' => $user->id,
            'approved_at' => now(),
        ]);

        return response()->json([
            'message' => 'Purchase approved successfully.',
            'data' => $purchase->load(['vendor', 'item', 'shift'])
        ], 200);
    }

    /**
     * Reject a purchase
     * Uses 'purchase.approve' permission as defined in middleware()
     */
    public function reject(Request $request, Purchase $purchase): JsonResponse
    {
        $user = Auth::user();

        
        $validated = $request->validate([
            'reason' => 'required|string|max:255',
        ]);

         
        if ($purchase->status !== 'pending') {
            return response()->json([
                'message' => "This purchase cannot be rejected. Current status: {$purchase->status}"
            ], 422);
        }

        
        $purchase->update([
            'status'      => 'rejected',
            'approved_by' => $user->id,  
            'approved_at' => now(),
            'notes'       => $validated['reason'],  
        ]);

        return response()->json([
            'message' => 'Purchase rejected successfully.',
            'data'    => $purchase->load(['vendor', 'item', 'shift'])
        ], 200);
    }

    public function pay($purchaseId)
{
    try {
        $purchase = Purchase::findOrFail($purchaseId);
    
        if($purchase->status !== 'approved'){
            return response()->json([
                'message' => 'Purchase must be approved'
            ], 422);
        }
        
        // Check if already paid
        if($purchase->status === 'paid' || $purchase->mpesa_receipt_number) {
            return response()->json([
                'message' => 'Purchase already paid',
                'receipt' => $purchase->mpesa_receipt_number
            ], 422);
        }
        
        // Check if payment is already processing
        if($purchase->payment_status === 'processing' && $purchase->mpesa_checkout_id) {
            return response()->json([
                'message' => 'Payment already in progress',
                'checkout_id' => $purchase->mpesa_checkout_id
            ], 422);
        }
    
        MpesaStkPushJob::dispatch($purchase->id);
    
        return response()->json([
            'message' => 'Payment request queued successfully',
            'purchase_id' => $purchaseId
        ]);
        
    } catch (\Exception $e) {
        Log::error('Pay endpoint error: ' . $e->getMessage());
        return response()->json([
            'message' => 'Error processing payment request'
        ], 500);
    }
}

/**
 * Record a sale for an existing purchase
 */
public function sell(Request $request, $purchaseId): JsonResponse
{
    $user = Auth::user();

    $validated = $request->validate([
        'selling_unit_price' => 'required|numeric|min:0.01',
    ]);

    try {
        return DB::transaction(function () use ($validated, $user, $purchaseId) {
            // 1. Find and lock the purchase record
            $purchase = Purchase::with('shift')->lockForUpdate()->findOrFail($purchaseId);

            // 2. Validate status and availability
            if ($purchase->status !== 'approved') {
                return response()->json(['message' => 'Only approved purchases can be sold.'], 422);
            }

            if ($purchase->is_sold) {
                return response()->json(['message' => 'This purchase has already been sold.'], 422);
            }

            if (!$purchase->shift) {
                return response()->json(['message' => 'Purchase shift record is missing.'], 422);
            }

            $sellingPrice = floatval($validated['selling_unit_price']);
            $costPrice = floatval($purchase->unit_price);

            // 3. Business Logic: Prevent selling at a loss if required
            if ($sellingPrice < $costPrice) {
                return response()->json([
                    'message' => 'Selling price cannot be lower than the cost price.',
                    'cost_price' => number_format($costPrice, 2)
                ], 422);
            }

            $totalSales = $purchase->quantity * $sellingPrice;
            $profit = $totalSales - $purchase->total_amount;

            // 4. Update Purchase Record
            $purchase->update([
                'selling_unit_price' => $sellingPrice,
                'sales_amount'       => $totalSales,
                'gross_profit'       => $profit,
                'is_sold'            => true,
                'sold_at'            => now(),
                'sold_by'            => $user->id,
            ]);

            // 5. Create the Sale Record
            $sale = \App\Models\Sale::create([
                'purchase_id'        => $purchase->id,
                'quantity'           => $purchase->quantity,
                'selling_unit_price' => $sellingPrice,
                'sales_amount'       => $totalSales,
                'cost_amount'        => $purchase->total_amount,
                'profit'             => $profit,
                'sold_by'            => $user->id,
                'company_id'         => $purchase->shift->company_id,
            ]);

            return response()->json([
                'message' => 'Sale processed successfully.',
                'data'    => [
                    'purchase_id' => $purchase->id,
                    'sale_id'     => $sale->id,
                    'revenue'     => number_format($totalSales, 2),
                    'profit'      => number_format($profit, 2),
                ]
            ], 200);
        });
    } catch (Exception $e) {
        return response()->json([
            'message' => 'Failed to process sale.',
            'error'   => $e->getMessage()
        ], 500);
    }
} 

}  
