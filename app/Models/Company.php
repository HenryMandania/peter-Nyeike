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
}