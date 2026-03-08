<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Sale extends Model
{
    protected $fillable = [
        'purchase_id',
        'quantity',
        'selling_unit_price',
        'sales_amount', 
        'cost_amount',
        'profit',
        'seller_id',
        'sold_by',
        'company_id',
        'sold_at',
        'created_at',
        'updated_at',
    ];

    public function purchase()
    {
        return $this->belongsTo(Purchase::class);
    }

    public function seller()
    {
        return $this->belongsTo(User::class,'sold_by');
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }
}