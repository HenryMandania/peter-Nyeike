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

    public function initiateMobilePayment(Request $request)
    {
        // Validate: purchase_id is required, phone is required from the app
        $request->validate([
            'purchase_id' => 'required|exists:purchases,id',
            'phone'       => 'required|string',
        ]);

        try {
            // Fetch the model object
            $purchase = Purchase::findOrFail($request->purchase_id);

            // Pass the model and the customer's phone to the service
            $result = $this->mpesaService->processPayment($purchase, $request->phone);

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