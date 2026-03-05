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
        Schema::create('mpesa_transactions', function (Blueprint $table) {
            $table->id();
            
            // Relationship to the original record (Purchase, Expense, or FloatRequest)
            $table->nullableMorphs('transactionable'); 
            
            // Transaction Details
            $table->string('type'); // 'purchase', 'expense', 'float_request'
            $table->string('mpesa_receipt_number')->nullable()->unique();
            $table->string('checkout_request_id')->unique()->index();
            $table->decimal('amount', 15, 2);
            $table->string('phone_number');
            
            // Status Tracking
            $table->enum('status', ['requested', 'completed', 'failed', 'cancelled'])->default('requested');
            $table->string('result_desc')->nullable(); // Safaricom's feedback message
            
            // Metadata
            $table->json('raw_callback_payload')->nullable(); // Store the full JSON for debugging
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mpesa_transactions');
    }
};
