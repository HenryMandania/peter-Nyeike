<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class FloatRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'shift_id',
        'amount',
        'status',
        'approved_by',
    ];

    public function user(): BelongsTo 
    { 
        return $this->belongsTo(User::class); 
    }

    public function shift(): BelongsTo 
    { 
        return $this->belongsTo(Shift::class); 
    }

    public function approver(): BelongsTo 
    { 
        return $this->belongsTo(User::class, 'approved_by'); 
    }

    public static function getLastClosingBalance($userId)
    {
        $lastShift = Shift::where('user_id', $userId)
            ->where('status', 'closed')
            ->orderBy('closed_at', 'desc')
            ->first();

        return $lastShift ? $lastShift->closing_balance : 0;
    }

   
    public function mpesaTransactions()
    {
        return $this->morphMany(\App\Models\MpesaTransaction::class, 'transactionable');
    }
}