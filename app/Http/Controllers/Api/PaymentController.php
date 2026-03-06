<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\MpesaService;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    protected $mpesaService;

    public function __construct(MpesaService $mpesaService)
    {
        $this->mpesaService = $mpesaService;
    }

    public function initiateMobilePayment(Request $request)
    {
        // 1. Validate incoming data from mobile app
        $request->validate([
            'phone' => 'required|string',
            'amount' => 'required|numeric',
            'purchase_id' => 'required|exists:purchases,id',
        ]);

        // 2. Trigger the payment service
        try {
            $result = $this->mpesaService->processPayment(
                $request->phone, 
                $request->amount, 
                $request->purchase_id
            );

            return response()->json([
                'success' => true,
                'message' => 'STK Push sent successfully',
                'data' => $result
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Payment initiation failed: ' . $e->getMessage()
            ], 500);
        }
    }
}