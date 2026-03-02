<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Supplier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule; // Import the Rule class

class SupplierController extends Controller
{
    // List all suppliers
    public function index()
    {
        $suppliers = Supplier::latest()->get();
        return response()->json($suppliers);
    }

    // Create a new supplier
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'     => 'required|string|max:255',
            'phone'    => 'required|string|unique:vendors,phone', // Check unique in 'vendors' table
            'location' => 'nullable|string',
        ]);

        $supplier = Supplier::create([
            ...$validated,
            'created_by' => Auth::id(),
            'date_of_creating' => now(),
        ]);

        return response()->json([
            'message' => 'Supplier created successfully',
            'data'    => $supplier
        ], 201);
    }

    // Update an existing supplier
    public function update(Request $request, Supplier $supplier)
    {
        $validated = $request->validate([
            'name'     => 'sometimes|required|string|max:255',
            'phone'    => [
                'sometimes', 
                'required', 
                'string', 
                Rule::unique('vendors', 'phone')->ignore($supplier->id) // Ignore current supplier ID
            ],
            'location' => 'nullable|string',
        ]);

        $supplier->update($validated);

        return response()->json([
            'message' => 'Supplier updated successfully',
            'data'    => $supplier
        ]);
    }
}