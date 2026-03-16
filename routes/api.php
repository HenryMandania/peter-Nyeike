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
use App\Http\Controllers\Api\PaymentController;

 
Route::post('/mpesa/callback', [MpesaCallbackController::class, 'handle'])->name('mpesa.callback');
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
       
    
    Route::middleware(['auth:sanctum'])->group(function () {
    Route::post('/float-requests', [FloatRequestController::class, 'store']);
    Route::get('/float-requests/pending', [FloatRequestController::class, 'pending'])->middleware('permission:float-request.view');
    Route::post('/float-requests/{floatRequest}/approve', [FloatRequestController::class, 'approve'])->middleware('permission:float-request.approve');
    Route::post('/float-requests/{floatRequest}/reject', [FloatRequestController::class, 'reject'])->middleware('permission:float-request.reject');
    });

    Route::post('/expenses', [ExpenseController::class, 'store']);
    Route::get('/expenses', [ExpenseController::class, 'index']);

    Route::get('/metadata/payment-methods', [MetadataController::class, 'getPaymentMethods']);
    Route::get('/metadata/expense-categories', [MetadataController::class, 'getExpenseCategories']);  

    Route::post('/logout', [AuthController::class, 'logout']);

    Route::post('/mpesa/vendor-payment', [PaymentController::class, 'initiateVendorPayment']);
    Route::post('/mpesa/purchase-message', [PaymentController::class, 'capturePurchaseMpesaMessage']);
    Route::post('/mpesa/float-request-message', [PaymentController::class, 'captureFloatRequestMpesaMessage']);
    Route::post('/mpesa/expense-message', [ExpenseController::class, 'captureExpenseMpesaMessage']);
});