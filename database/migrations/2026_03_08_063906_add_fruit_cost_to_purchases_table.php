<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchases', function (Blueprint $table) {

            // Actual fruit cost before fees (Qty * Unit Price)
            $table->decimal('fruit_cost', 15, 2)->default(0)->after('unit_price');

        });
    }

    public function down(): void
    {
        Schema::table('purchases', function (Blueprint $table) {
            $table->dropColumn('fruit_cost');
        });
    }
};