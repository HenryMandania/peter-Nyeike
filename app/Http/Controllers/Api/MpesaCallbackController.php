<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessDisbursementCallbackJob;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class MpesaCallbackController extends Controller
{
    public function handle(Request $request)
    {
        $data = $request->all();
        Log::info('M-PESA Callback Received', $data);

        // For B2C/B2B disbursements (Result key present)
        if (isset($data['Result'])) {
            ProcessDisbursementCallbackJob::dispatch($data);
        }
        // Add elseif for STK if you implement later: isset($data['Body']['stkCallback'])

        else {
            Log::warning('Unknown callback format', $data);
        }

        // Always respond with success to Safaricom
        return response()->json([
            'ResultCode' => 0,
            'ResultDesc' => 'Accepted'
        ]);
    }
}