<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MpesaConfig extends Model
{ 
    // It is safer to use $fillable than $guarded
    protected $fillable = [
        'name',
        'consumer_key',
        'consumer_secret',
        'shortcode',
        'paying_number',
        'passkey',
        'callback_url',
        'timeout_url',
        'result_url',
        'env',
        'initiator_name',
        'initiator_password',
        'security_credential',
        'is_active'
    ];

    /**
     * Automatically encrypt the initiator password in the database.
     * When you retrieve it, Laravel will decrypt it automatically.
     */
    protected $casts = [
        'initiator_password' => 'encrypted',
        'is_active' => 'boolean',
    ];
}