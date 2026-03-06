<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('mpesa_configs', function (Blueprint $table) {
            // Initiator details are required for B2B/B2C
            $table->string('initiator_name')->nullable()->after('passkey');
            $table->string('initiator_password')->nullable()->after('initiator_name');
            
            // The Security Credential is the encrypted version of the password
            // We use 'text' because encrypted strings can be long
            $table->text('security_credential')->nullable()->after('initiator_password');
            
            // URLs for asynchronous callback handling
            $table->string('timeout_url')->nullable()->after('callback_url');
            $table->string('result_url')->nullable()->after('timeout_url');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('mpesa_configs', function (Blueprint $table) {
            $table->dropColumn([
                'initiator_name', 
                'initiator_password', 
                'security_credential', 
                'timeout_url', 
                'result_url'
            ]);
        });
    }
};