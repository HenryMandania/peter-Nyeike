<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ExpenseCategory;

class MetadataController extends Controller
{
    public function getPaymentMethods()
    {
        // We return an array of objects to make it easy for mobile pickers
        return response()->json([
            'payment_methods' => [
                ['id' => 'Cash', 'label' => 'Cash'],
                ['id' => 'Mpesa', 'label' => 'Mpesa'],
                ['id' => 'Bank', 'label' => 'Bank'],
            ]
        ]);
    }

    public function getExpenseCategories()
    {
        // This solves your previous "required" error by letting the phone
        // fetch the actual IDs currently in your database.
        return response()->json(
            ExpenseCategory::select('id', 'name')->get()
        );
    }
}