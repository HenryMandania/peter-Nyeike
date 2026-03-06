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
                Schema::table('mpesa_transactions', function ($table) {
                    // This creates an index on both columns combined
                    $table->index(['transactionable_id', 'transactionable_type'], 'idx_transactionable');
                });
            }

            public function down(): void
            {
                Schema::table('mpesa_transactions', function ($table) {
                    $table->dropIndex('idx_transactionable');
                });
            }
};
