<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Supplier extends Model
{
    use HasFactory;

    protected $table = 'vendors'; // Mapping to your migration table name

    protected $fillable = [
        'name',
        'phone',
        'location',
        'created_by',
        'date_of_registration',
        'date_of_creating',
    ];

    protected $casts = [
        'date_of_registration' => 'date',
        'date_of_creating'     => 'date',
    ];

    /**
     * User who created the supplier
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Supplier Purchase History
     */
    public function purchases(): HasMany
    {
        return $this->hasMany(Purchase::class, 'vendor_id');
    }
}