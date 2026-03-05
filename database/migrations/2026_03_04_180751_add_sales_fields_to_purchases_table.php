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
        Schema::table('purchases', function (Blueprint $table) {
            // Tracking Sale Status & User
            $table->boolean('is_sold')->default(false)->after('status');
            $table->timestamp('sold_at')->nullable()->after('is_sold');
            $table->foreignId('sold_by')->nullable()->constrained('users')->nullOnDelete()->after('sold_at');

            // Financial Data for Sales
            $table->decimal('selling_unit_price', 15, 2)->nullable()->after('sold_by'); 
            $table->decimal('sales_amount', 15, 2)->nullable()->after('selling_unit_price'); 
            $table->decimal('gross_profit', 15, 2)->nullable()->after('sales_amount'); 
            
            // Indexing for performance on reports
            $table->index(['is_sold', 'sold_at', 'sold_by']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('purchases', function (Blueprint $table) {
            $table->dropForeign(['sold_by']); // Drop foreign key first
            $table->dropColumn([
                'is_sold',
                'sold_at',
                'sold_by',
                'selling_unit_price',
                'sales_amount',
                'gross_profit'
            ]);
        });
    }
};