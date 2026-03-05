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
        Schema::table('mpesa_transactions', function (Blueprint $table) {

            // Fast lookup for Safaricom callbacks
            $table->index('checkout_request_id');

            // Fast lookup for related model transactions
            $table->index(['transactionable_id', 'transactionable_type'], 'mpesa_transactionable_index');

            // Fast filtering for pending/failed/completed transactions
            $table->index('status');

            // Optional but useful for reports
            $table->index('created_at');

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('mpesa_transactions', function (Blueprint $table) {

            $table->dropIndex(['checkout_request_id']);

            $table->dropIndex('mpesa_transactionable_index');

            $table->dropIndex(['status']);

            $table->dropIndex(['created_at']);

        });
    }
};