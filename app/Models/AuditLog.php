<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AuditLog extends Model
{
    protected $table = 'audit_logs';
    protected $guarded = [];

    /**
     * The attributes that should be cast to native types.
     * * Adding 'array' here tells Laravel to json_decode() these 
     * automatically when you fetch them.
     */
    protected $casts = [
        'old_values' => 'array',
        'new_values' => 'array',
    ];
}