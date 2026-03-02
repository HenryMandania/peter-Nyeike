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
        Schema::create('purchases', function (Blueprint $table) {
            $table->id();
        
            // Core Relationships
            $table->foreignId('shift_id')->constrained()->cascadeOnDelete();
            $table->foreignId('vendor_id')->constrained();
            $table->foreignId('item_id')->constrained();
        
            // Financial Data
            $table->decimal('quantity', 15, 2);
            $table->decimal('unit_price', 15, 2);
            $table->decimal('total_amount', 15, 2); // (Quantity * Price)
            $table->decimal('transaction_fee', 15, 2)->default(0); 
            
            // Payment & Tracking
            $table->enum('payment_method', ['Mpesa', 'Cash', 'Bank'])->default('Cash');
            $table->string('reference_no')->nullable(); 
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->text('notes')->nullable();

            // Audit Trail
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
        
            $table->timestamps();

            // Indexing for performance and reconciliation
            $table->index(['shift_id', 'status']);
            $table->index('reference_no');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchases');
    }
};