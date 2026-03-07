<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FloatRequest;
use App\Models\Shift;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class FloatRequestController extends Controller
{
   
    public function pending()
    {
        $user = Auth::user();

        // Fetch pending float requests
        $pendingRequests = FloatRequest::with(['user', 'shift'])
            ->where('status', 'pending')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'message' => 'Pending float requests fetched successfully.',
            'data' => $pendingRequests
        ], 200);
    }

    /**
     * Approve a float request.
     * Middleware 'permission:float-request.approve' should handle authorization.
     */
    public function approve(FloatRequest $floatRequest)
{
    $user = Auth::user();

    if ($floatRequest->user_id === $user->id) {
        return response()->json(['message' => 'You cannot approve your own request.'], 403);
    }

    // Wrap the result of the transaction
    $response = DB::transaction(function () use ($floatRequest, $user) {
        $floatRequest = FloatRequest::where('id', $floatRequest->id)->lockForUpdate()->first();

        if ($floatRequest->status !== 'pending') {
            return ['status' => 422, 'message' => 'This request has already been processed.'];
        }

        $shift = Shift::where('id', $floatRequest->shift_id)->lockForUpdate()->first();

        if (!$shift || $shift->status !== 'open') {
            return ['status' => 422, 'message' => 'Associated shift not found or already closed.'];
        }

        $shift->increment('float_balance', $floatRequest->amount);

        $floatRequest->update([
            'status' => 'approved',
            'approved_by' => $user->id,
            'approved_at' => now(),
        ]);

        return ['status' => 200, 'message' => 'Float request approved successfully.', 'data' => $floatRequest->load(['user', 'shift'])];
    });

    return response()->json($response, $response['status']);
}

    /**
     * Reject a float request.
     * Middleware 'permission:float-request.reject' should handle authorization.
     */
    public function reject(FloatRequest $floatRequest)
    {
        $user = Auth::user();

        if ($floatRequest->status !== 'pending') {
            return response()->json([
                'message' => 'This request has already been processed.'
            ], 422);
        }

        $floatRequest->update([
            'status' => 'rejected',
            'approved_by' => $user->id,
            'approved_at' => now(),
        ]);

        return response()->json([
            'message' => 'Float request rejected successfully.'
        ], 200);
    }

    public function store(Request $request)
{
    $user = Auth::user();

    $validated = $request->validate([
        'amount' => ['required', 'numeric', 'min:1']
    ]);

    // Get the user's open shift
    $shift = Shift::where('user_id', $user->id)
        ->where('status', 'open')
        ->first();

    if (!$shift) {
        return response()->json([
            'message' => 'No open shift found for this user.'
        ], 422);
    }

    $floatRequest = FloatRequest::create([
        'user_id' => $user->id,
        'shift_id' => $shift->id,
        'amount' => $validated['amount'],
        'status' => 'pending'
    ]);

    return response()->json([
        'message' => 'Float request submitted successfully.',
        'data' => $floatRequest
    ], 201);
}
}