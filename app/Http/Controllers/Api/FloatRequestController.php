<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FloatRequest;
use App\Models\Shift;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class FloatRequestController extends Controller
{
    public function index()
    {
        return response()->json(
            FloatRequest::where('user_id', Auth::id())
                ->with(['approver:id,name', 'shift:id,status'])
                ->latest()
                ->paginate(15)
        );
    }

    public function store(Request $request)
    {
        $user = Auth::user();

        $validated = $request->validate([
            'amount' => 'required|numeric|min:1',
        ]);

        $activeShift = Shift::where('user_id', $user->id)
            ->where('status', 'open')
            ->first();

        if (!$activeShift) {
            return response()->json([
                'message' => 'No active shift found. Please start a shift to request float.'
            ], 403);
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

    /**
     * For Supervisors: Get all requests waiting for approval
     */
    public function pending()
    {
        return response()->json(
            FloatRequest::where('status', 'pending')
                ->with('user:id,name')
                ->latest()
                ->get()
        );
    }

    public function approve(FloatRequest $floatRequest)
    {
        if ($floatRequest->status !== 'pending') {
            return response()->json(['message' => 'This request has already been processed.'], 422);
        }

        $floatRequest->update([
            'status' => 'approved',
            'approved_by' => Auth::id(),
        ]);

        return response()->json([
            'message' => 'Float request approved successfully.',
            'data' => $floatRequest->load('approver')
        ]);
    }

    public function reject(FloatRequest $floatRequest)
    {
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