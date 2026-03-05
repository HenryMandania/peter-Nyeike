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
        Schema::create('mpesa_configs', function (Blueprint $table) {
            $table->id();
            $table->string('name')->default('Primary Account'); // Branch/Store Name
            
            // M-Pesa Credentials
            $table->string('consumer_key');
            $table->string('consumer_secret');
            $table->string('shortcode');
            $table->string('passkey');
            $table->enum('env', ['sandbox', 'live'])->default('sandbox');
            
            // Your New Columns
            $table->string('customer')->nullable();       // Customer/Company Name
            $table->string('contact_person')->nullable(); // Name of focal person
            $table->string('email')->nullable();          // Notification/Contact Email
            $table->string('pass')->nullable();           // Portal Password (Encrypted)
            
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mpesa_configs');
    }
};
