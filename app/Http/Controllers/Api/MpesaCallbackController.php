<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Jobs\ProcessMpesaCallbackJob;
use Illuminate\Support\Facades\Log;

class MpesaCallbackController extends Controller
{
    public function handle(Request $request)
    {
        $data = $request->all();
        Log::info('Mpesa Callback Payload:', $data);

        
        if (!isset($data['Body']['stkCallback'])) {
            Log::error("M-Pesa Callback: Missing Body/stkCallback");
            return response()->json(['ResultCode' => 1, 'ResultDesc' => 'Invalid Payload']);
        }

 
        ProcessMpesaCallbackJob::dispatch($data);

  
        return response()->json([
            'ResponseCode' => '00000000',
            'ResponseDesc' => 'success'
        ]);
    }
}