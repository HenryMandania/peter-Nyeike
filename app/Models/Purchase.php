<?php

namespace App\Models;

use App\Services\BalanceService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class Purchase extends Model
{
    protected $fillable = [
        'shift_id',
        'company_id',
        'vendor_id',
        'item_id',
        'quantity',
        'unit_price',
        'fruit_cost',
        'total_amount',
        'transaction_fee',
        'payment_method',
        'created_by',
        'updated_by',
        'approved_by',
        'status',
        'notes',
        'reference_no',
        'selling_unit_price',
        'sales_amount',
        'gross_profit',
        'is_sold',
        'sold_at',
        'sold_by',
        'mpesa_checkout_id',
        'mpesa_receipt_number',
        'mpesa_phone',
        'payment_status'
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
                throw ValidationException::withMessages([
                    'vendor_id' => 'No active shift found.'
                ]);
            }

            // Calculate fruit cost
            $purchase->fruit_cost = $purchase->quantity * $purchase->unit_price;

            // Total cost (fruit + fees)
            $purchase->total_amount = $purchase->fruit_cost + ($purchase->transaction_fee ?? 0);

            $service = app(BalanceService::class);
            $available = $service->calculate($activeShift);

            if ($purchase->total_amount > $available) {
                throw ValidationException::withMessages([
                    'quantity' => "INSUFFICIENT BALANCE. Available: KES " . number_format($available, 2)
                ]);
            }

            $purchase->shift_id = $activeShift->id;
            $purchase->created_by = Auth::id();
            $purchase->company_id = $activeShift->company_id;
        });

        static::updating(function ($purchase) {

            $purchase->updated_by = Auth::id();

            // Recalculate costs
            $purchase->fruit_cost = $purchase->quantity * $purchase->unit_price;

            $purchase->total_amount =
                $purchase->fruit_cost + ($purchase->transaction_fee ?? 0);
        });
    }

    // Relationships

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Supplier::class, 'vendor_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class, 'item_id');
    }

    public function operator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class);
    }

    public function seller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sold_by');
    }

    public function mpesaTransactions(): MorphMany
    {
        return $this->morphMany(MpesaTransaction::class, 'transactionable');
    }
   
    public function sale()
    {
        return $this->hasOne(Sale::class);
    }
 
    public function company()
    { 
        return $this->belongsTo(\App\Models\Company::class);
    }
    
    public function creator()
    {        
        return $this->belongsTo(\App\Models\User::class, 'created_by');
    }
}