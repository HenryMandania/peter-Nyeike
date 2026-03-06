<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\SessionController;
use App\Http\Controllers\Api\SupplierController;
use App\Http\Controllers\Api\PurchaseController;
use App\Http\Controllers\Api\ItemController;
use App\Http\Controllers\Api\FloatRequestController;
use App\Http\Controllers\Api\ExpenseController;
use App\Http\Controllers\Api\MetadataController;
use App\Http\Controllers\Api\MpesaCallbackController;
use Illuminate\Support\Facades\DB;
use App\Models\MpesaTransaction;
use App\Models\Purchase;
use App\Jobs\ProcessMpesaCallbackJob;

Route::post('/mpesa/callback', [MpesaCallbackController::class, 'handle']);
Route::post('/login', [AuthController::class, 'login'])->name('login');

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', function (Request $request) {
        return $request->user();
    });

    Route::get('/session/status', [SessionController::class, 'status']);
    Route::post('/session/open', [SessionController::class, 'open']);
    Route::post('/session/close', [SessionController::class, 'close']);

    Route::get('/suppliers', [SupplierController::class, 'index']);
    Route::post('/suppliers', [SupplierController::class, 'store']);
    Route::put('/suppliers/{supplier}', [SupplierController::class, 'update']);

  
    Route::middleware(['auth:sanctum'])->group(function () {    
    Route::get('/purchases', [PurchaseController::class, 'index'])->middleware('permission:purchase.view');
    Route::post('/purchases', [PurchaseController::class, 'store'])->middleware('permission:purchase.create');
    Route::post('/purchases/{purchase}/approve', [PurchaseController::class, 'approve'])->middleware('permission:purchase.approve');
    Route::post('/purchases/{purchase}/pay', [PurchaseController::class, 'pay'])->middleware('permission:purchase.approve');
    Route::post('/purchases/{purchase}/sell', [PurchaseController::class, 'sell'])->middleware('permission:purchase.approve');
    Route::post('/purchases/{purchase}/reject', [PurchaseController::class, 'approve'])->middleware('permission:purchase.approve');});  

    Route::get('/items', [ItemController::class, 'index']);
       
    Route::get('/float-requests', [FloatRequestController::class, 'index']);
    Route::post('/float-requests', [FloatRequestController::class, 'store']);
    
    Route::middleware(['auth:sanctum'])->group(function () {         
    Route::get('/float-requests/pending', [FloatRequestController::class, 'pending'])->middleware('permission:float-request.view');
    Route::post('/float-requests/{floatRequest}/approve', [FloatRequestController::class, 'approve'])->middleware('permission:float-request.approve');
    Route::post('/float-requests/{floatRequest}/reject', [FloatRequestController::class, 'reject'])->middleware('permission:float-request.reject');
    Route::get('/float-requests/pending', [FloatRequestController::class, 'pending'])->middleware('permission:float-request.view');});

    Route::post('/expenses', [ExpenseController::class, 'store']);
    Route::get('/expenses', [ExpenseController::class, 'index']);

    Route::get('/metadata/payment-methods', [MetadataController::class, 'getPaymentMethods']);
    Route::get('/metadata/expense-categories', [MetadataController::class, 'getExpenseCategories']);  

    Route::post('/logout', [AuthController::class, 'logout']);


        // Add to routes/api.php
        Route::post('/test-callback-manual', function(Request $request) {
            $checkoutId = $request->checkout_id;
            
            // Validate input
            if (!$checkoutId) {
                return response()->json(['error' => 'checkout_id is required'], 400);
            }
            
            try {
                // Find the transaction
                $transaction = MpesaTransaction::where('checkout_request_id', $checkoutId)->first();
                
                if (!$transaction) {
                    return response()->json([
                        'error' => 'Transaction not found',
                        'checkout_id' => $checkoutId
                    ], 404);
                }
                
                // Manually trigger the update in a transaction
                DB::transaction(function() use ($transaction) {
                    // Update MpesaTransaction
                    $transaction->update([
                        'status' => 'completed',
                        'mpesa_receipt_number' => 'MANUAL_' . time(),
                        'result_desc' => 'Manual update',
                        'completed_at' => now()
                    ]);
                    
                    // Update the related purchase
                    $purchase = Purchase::find($transaction->transactionable_id);
                    if ($purchase) {
                        $purchase->update([
                            'status' => 'paid',
                            'payment_status' => 'paid',
                            'mpesa_receipt_number' => $transaction->mpesa_receipt_number,
                            'mpesa_error_message' => null
                        ]);
                    }
                });
                
                return response()->json([
                    'message' => 'Manual update completed',
                    'transaction_id' => $transaction->id,
                    'purchase_id' => $transaction->transactionable_id,
                    'receipt' => $transaction->mpesa_receipt_number
                ]);
                
            } catch (\Exception $e) {
                return response()->json([
                    'error' => 'Update failed',
                    'message' => $e->getMessage()
                ], 500);
            }
        });
});