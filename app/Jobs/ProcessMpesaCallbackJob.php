<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Jobs\ProcessMpesaCallbackJob;
use App\Jobs\ProcessB2B2CCallbackJob;
use Illuminate\Support\Facades\Log;

class MpesaCallbackController extends Controller
{
    public function handle(Request $request)
    {
        $data = $request->all();
        Log::info("MPESA CALLBACK RECEIVED", $data);

        if (isset($data['Body']['stkCallback'])) {
            ProcessMpesaCallbackJob::dispatch($data);
        } elseif (isset($data['Result'])) {
            ProcessB2B2CCallbackJob::dispatch($data);
        } else {
            Log::warning("Unknown M-Pesa callback format received", $data);
        }

        return response()->json([
            'ResultCode' => 0,
            'ResultDesc' => 'Accepted'
        ]);
    }
}
