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

    Route::get('/purchases', [PurchaseController::class, 'index']);
    Route::post('/purchases', [PurchaseController::class, 'store']);

    Route::get('/items', [ItemController::class, 'index']);
       
    Route::get('/float-requests', [FloatRequestController::class, 'index']);
    Route::post('/float-requests', [FloatRequestController::class, 'store']);
    
    Route::get('/float-requests/pending', [FloatRequestController::class, 'pending']);
    Route::post('/float-requests/{floatRequest}/approve', [FloatRequestController::class, 'approve']);
    Route::post('/float-requests/{floatRequest}/reject', [FloatRequestController::class, 'reject']);

    Route::post('/expenses', [ExpenseController::class, 'store']);
    Route::get('/expenses', [ExpenseController::class, 'index']);

    Route::get('/metadata/payment-methods', [MetadataController::class, 'getPaymentMethods']);
    Route::get('/metadata/expense-categories', [MetadataController::class, 'getExpenseCategories']);

    Route::post('/logout', [AuthController::class, 'logout']);
});