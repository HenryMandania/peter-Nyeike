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
    // ... index() remains fine ...

    public function store(Request $request)
    {
        $user = Auth::user();

        $validated = $request->validate([
            'amount' => 'required|numeric|min:1',
        ]);

        // Loophole Fix: Check if there is already a PENDING request 
        // to prevent "spamming" the supervisor.
        $hasPending = FloatRequest::where('user_id', $user->id)
            ->where('status', 'pending')
            ->exists();

        if ($hasPending) {
            return response()->json(['message' => 'You already have a pending float request.'], 422);
        }

        $activeShift = Shift::where('user_id', $user->id)
            ->where('status', 'open')
            ->first();

        if (!$activeShift) {
            return response()->json(['message' => 'No active shift found.'], 403);
        }

        $floatRequest = FloatRequest::create([
            'user_id' => $user->id,
            'shift_id' => $activeShift->id,
            'amount' => $validated['amount'],
            'status' => 'pending',
        ]);

        return response()->json([
            'message' => 'Float request submitted successfully.',
            'data' => $floatRequest
        ], 201);
    }

    public function approve(FloatRequest $floatRequest)
    {
        // Loophole Fix 1: Prevent Self-Approval
        if ($floatRequest->user_id === Auth::id()) {
            return response()->json(['message' => 'You cannot approve your own float request.'], 403);
        }

        // Loophole Fix 2: Check status
        if ($floatRequest->status !== 'pending') {
            return response()->json(['message' => 'This request has already been processed.'], 422);
        }

        // Loophole Fix 3: Ensure the shift is still OPEN
        // If the shift is closed, approving this float will create a balance mismatch.
        if ($floatRequest->shift->status !== 'open') {
            return response()->json(['message' => 'The shift associated with this request is already closed.'], 422);
        }

        return DB::transaction(function () use ($floatRequest) {
            $floatRequest->update([
                'status' => 'approved',
                'approved_by' => Auth::id(),
            ]);

            return response()->json([
                'message' => 'Float request approved successfully.',
                'data' => $floatRequest->load('approver')
            ]);
        });
    }

    public function reject(FloatRequest $floatRequest)
    {
        // Loophole Fix: Standard status check
        if ($floatRequest->status !== 'pending') {
            return response()->json(['message' => 'This request has already been processed.'], 422);
        }

        $floatRequest->update([
            'status' => 'rejected',
            'approved_by' => Auth::id(),
        ]);

        return response()->json(['message' => 'Float request rejected.']);
    }
}