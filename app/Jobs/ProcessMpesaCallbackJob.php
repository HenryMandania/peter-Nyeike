<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Jobs\ProcessMpesaCallbackJob; // Your existing STK Job
use App\Jobs\ProcessB2B2CCallbackJob; // The new Job we created
use Illuminate\Support\Facades\Log;

class MpesaCallbackController extends Controller
{
    /**
     * Handle incoming M-Pesa callbacks.
     * This controller acts as a router for different M-Pesa API response types.
     */
    public function handle(Request $request)
    {
        $data = $request->all();

        // 1. Log the incoming payload for audit purposes
        Log::info("MPESA CALLBACK RECEIVED", $data);

        // 2. Routing logic based on payload structure
        // STK Push payloads contain 'Body'
        if (isset($data['Body']['stkCallback'])) {
            ProcessMpesaCallbackJob::dispatch($data);
        } 
        
        // B2C/B2B result payloads contain 'Result'
        elseif (isset($data['Result'])) {
            ProcessB2B2CCallbackJob::dispatch($data);
        } 
        
        // Handle unexpected payloads
        else {
            Log::warning("Unknown M-Pesa callback format received", $data);
        }

        // 3. Return immediate acknowledgment to Safaricom
        // Safaricom expects a 200 OK response to stop resending the callback
        return response()->json([
            'ResultCode' => 0,
            'ResultDesc' => 'Accepted'
        ]);
    }
}