<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Company extends Model
{
    protected $fillable = [
        'name',
        'location',
        'contact_person',
    ];

    /**
     * A company has many work shifts.
     */
    public function shifts(): HasMany
    {
        return $this->hasMany(Shift::class);
    }

    /**
     * A company has many purchase transactions.
     */
    public function purchases(): HasMany
    {
        return $this->hasMany(Purchase::class);
    }

    /**
     * A company has many recorded payments.
     */
    public function payments(): HasMany
    {
        return $this->hasMany(CompanyPayment::class);
    }

    /*
    |--------------------------------------------------------------------------
    | Financial Accessors
    |--------------------------------------------------------------------------
    */

    /**
     * Calculate total value of all purchases (Sales/Expected).
     */
    public function getTotalPurchasesAttribute(): float
    {
        // Adjust 'total_amount' to match the actual column name in your purchases table
        return (float) $this->purchases()->sum('total_amount');
    }

    /**
     * Calculate total amount paid by the company.
     */
    public function getTotalPaidAttribute(): float
    {
        return (float) $this->payments()->sum('amount');
    }

    /**
     * Calculate the current outstanding balance.
     */
    public function getBalanceAttribute(): float
    {
        return $this->total_purchases - $this->total_paid;
    }
}