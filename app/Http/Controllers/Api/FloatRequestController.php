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

        // Prevent self approval
        if ($floatRequest->user_id === $user->id) {
            return response()->json([
                'message' => 'You cannot approve your own float request.'
            ], 403);
        }

        return DB::transaction(function () use ($floatRequest, $user) {

            // Lock row to prevent double approval
            $floatRequest = FloatRequest::where('id', $floatRequest->id)
                ->lockForUpdate()
                ->first();

            if (!$floatRequest) {
                return response()->json([
                    'message' => 'Float request not found.'
                ], 404);
            }

            if ($floatRequest->status !== 'pending') {
                return response()->json([
                    'message' => 'This request has already been processed.'
                ], 422);
            }

            // Validate shift exists and is open
            $shift = Shift::where('id', $floatRequest->shift_id)
                ->lockForUpdate()
                ->first();

            if (!$shift) {
                return response()->json([
                    'message' => 'Associated shift not found.'
                ], 422);
            }

            if ($shift->status !== 'open') {
                return response()->json([
                    'message' => 'The associated shift is already closed.'
                ], 422);
            }

            // Update shift balance
            $shift->increment('float_balance', $floatRequest->amount);

            // Approve request
            $floatRequest->update([
                'status' => 'approved',
                'approved_by' => $user->id,
                'approved_at' => now(),
            ]);

            return response()->json([
                'message' => 'Float request approved successfully.',
                'data' => $floatRequest->load(['user', 'approver', 'shift'])
            ], 200);
        });
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