<?php

namespace App\Models;

use App\Services\BalanceService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Purchase extends Model
{
    protected $fillable = [
        'shift_id', 'company_id', 'vendor_id', 'item_id', 'quantity', 'unit_price',
        'total_amount', 'transaction_fee', 'payment_method', 'created_by', 'updated_by',
        'approved_by', 'status', 'notes', 'reference_no', 'selling_unit_price',
        'sales_amount', 'gross_profit', 'is_sold', 'sold_at', 'sold_by',
        // M-Pesa Tracking Fields
        'mpesa_checkout_id', 'mpesa_receipt_number', 'mpesa_phone', 'payment_status'
    ];

    protected $attributes = [
        'status' => 'pending',
        'transaction_fee' => 0,
        'payment_method' => 'Cash',
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

            // Calculation: (Qty * Price) + fee
            $netAmount = ($purchase->quantity * $purchase->unit_price);
            $transactionCost = $netAmount + $purchase->transaction_fee;

            $service = app(BalanceService::class);
            $available = $service->calculate($activeShift);

            if ($transactionCost > $available) {
                throw ValidationException::withMessages([
                    'quantity' => "INSUFFICIENT BALANCE. Available: KES " . number_format($available, 2)
                ]);
            }

            $purchase->shift_id     = $activeShift->id;
            $purchase->created_by   = Auth::id();
            $purchase->company_id   = $activeShift->company_id;
            $purchase->total_amount = $transactionCost;
        });

        static::updating(function ($purchase) {
            $purchase->updated_by   = Auth::id();
            $purchase->total_amount = ($purchase->quantity * $purchase->unit_price) + $purchase->transaction_fee;
        });
    }

    // Relationships
    public function vendor(): BelongsTo {
        // Linked to Supplier as per your code
        return $this->belongsTo(Supplier::class, 'vendor_id');
    }

    public function item(): BelongsTo { return $this->belongsTo(Item::class, 'item_id'); }
    public function operator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
    public function approver(): BelongsTo { return $this->belongsTo(User::class, 'approved_by'); }
    public function shift(): BelongsTo { return $this->belongsTo(Shift::class); }
    public function seller(): BelongsTo { return $this->belongsTo(User::class, 'sold_by'); }
    public function mpesaTransactions(): MorphMany
    {
        return $this->morphMany(MpesaTransaction::class, 'transactionable');
    }
}