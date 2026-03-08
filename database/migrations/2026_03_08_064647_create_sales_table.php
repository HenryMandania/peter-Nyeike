<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales', function (Blueprint $table) {
            $table->id();

            $table->foreignId('purchase_id')->constrained('purchases')->cascadeOnDelete();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();

            $table->decimal('quantity', 15,2);
            $table->decimal('selling_unit_price', 15,2);

            $table->decimal('sales_amount', 15,2);
            $table->decimal('cost_amount', 15,2);
            $table->decimal('profit', 15,2);

            $table->foreignId('sold_by')->constrained('users');
            $table->timestamp('sold_at')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales');
    }
};