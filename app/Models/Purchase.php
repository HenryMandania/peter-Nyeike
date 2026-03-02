<?php

namespace App\Models;

use App\Services\BalanceService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class Purchase extends Model
{
    protected $fillable = [
        'shift_id', 'vendor_id', 'item_id', 'quantity', 'unit_price', 
        'total_amount', 'transaction_fee', 'payment_method', 
        'created_by', 'updated_by', 'approved_by'
    ];

    protected static function booted(): void
    {
        static::creating(function ($purchase) {
            $activeShift = Shift::where('user_id', Auth::id())
                ->where('status', 'open')
                ->first();
            
            if (!$activeShift) {
                throw ValidationException::withMessages(['vendor_id' => 'No active shift found.']);
            }

            $transactionCost = ($purchase->quantity * $purchase->unit_price) + $purchase->transaction_fee;

            $service = app(BalanceService::class);
            $available = $service->calculate($activeShift);

            if ($transactionCost > $available) {
                throw ValidationException::withMessages([
                    'quantity' => "INSUFFICIENT BALANCE. Available: KES " . number_format($available, 2)
                ]);
            }

            $purchase->shift_id = $activeShift->id;
            $purchase->created_by = Auth::id();
            $purchase->total_amount = $transactionCost;
        });

        static::updating(function ($purchase) {
            $purchase->updated_by = Auth::id();
            $purchase->total_amount = ($purchase->quantity * $purchase->unit_price) + $purchase->transaction_fee;
        });
    }

    // Change 'Vendor' to 'Supplier' if that is your model name
    public function vendor(): BelongsTo { return $this->belongsTo(Supplier::class, 'vendor_id'); }
    public function item(): BelongsTo { return $this->belongsTo(Item::class, 'item_id'); }
    public function operator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
    public function approver(): BelongsTo { return $this->belongsTo(User::class, 'approved_by'); }
}