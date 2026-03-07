<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\MpesaService;
use App\Models\Purchase;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    protected $mpesaService;

    public function __construct(MpesaService $mpesaService)
    {
        $this->mpesaService = $mpesaService;
    }

    public function initiateVendorPayment(Request $request)
    {
        $request->validate(['purchase_id' => 'required|exists:purchases,id']);

        try {
            $purchase = Purchase::findOrFail($request->purchase_id);
            $result = $this->mpesaService->processVendorPayment($purchase);

            return response()->json([
                'success' => true,
                'message' => 'Payment request sent to M-Pesa.',
                'data' => $result
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }
}