<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Facades\Auth;

class Shift extends Model
{
    use HasFactory;

    protected $fillable = [
        'opening_balance',
        'closing_balance',
        'system_balance',
        'status',
        'opened_at',
        'closed_at',
        'user_id',
        'created_by',
    ];

    protected $casts = [
        'opened_at' => 'datetime',
        'closed_at' => 'datetime',
    ];

    protected static function booted(): void
{
    static::creating(function ($shift) {
        $shift->user_id = Auth::id();
        $shift->created_by = Auth::id();
        $shift->opened_at = now();
        $shift->status = 'open';

        // 1. Fetch last shift's closing balance
        $lastClosing = static::where('user_id', Auth::id())
            ->where('status', 'closed')
            ->orderBy('closed_at', 'desc')
            ->value('closing_balance') ?? 0;

        // 2. Set opening balance automatically if not manually provided
        $shift->opening_balance = $lastClosing;

        // 3. Sync initial system balance to the opening balance
        $shift->system_balance = $lastClosing;
        $shift->closing_balance = 0; // Reset closing until shift is actually closed
    });
}

    public function floatRequests(): HasMany
    {
        return $this->hasMany(FloatRequest::class);
    }

    public function purchases(): HasMany
    {
        return $this->hasMany(Purchase::class);
    }

    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class);
    }
    

    public function user(): BelongsTo { return $this->belongsTo(User::class, 'user_id'); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
}