<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class MpesaTransaction extends Model
{
    protected $fillable = [
        'transactionable_id',
        'transactionable_type',
        'type',
        'mpesa_receipt_number',
        'checkout_request_id',
        'amount',
        'phone_number',
        'status',
        'result_desc',
        'raw_callback_payload',
        'completed_at'
    ];

    protected $casts = [
        'raw_callback_payload' => 'array',
        'completed_at' => 'datetime',
    ];

    public function transactionable(): MorphTo
    {
        return $this->morphTo();
    }
}