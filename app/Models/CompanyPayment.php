<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class CompanyPayment extends Model
{
    protected $fillable = [
        'company_id',
        'amount',
        'payment_date',
        'payment_method',
        'reference_no',
    ];

    protected $casts = [
        'payment_date' => 'date',
        'amount' => 'decimal:2',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    // Add this method - it will be used by the relation manager
    public function purchases()
    {
        return $this->hasManyThrough(
            Purchase::class,
            Company::class,
            'id', // Foreign key on companies table
            'company_id', // Foreign key on purchases table
            'company_id', // Local key on company_payments table
            'id' // Local key on companies table
        );
    }
}