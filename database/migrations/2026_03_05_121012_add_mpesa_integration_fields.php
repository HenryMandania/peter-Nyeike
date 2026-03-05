<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Columns to be added to each table
        $columns = function (Blueprint $table) {
            $table->string('mpesa_checkout_id')->nullable()->index();
            $table->string('mpesa_phone')->nullable();
            $table->string('mpesa_receipt_number')->nullable();
            $table->text('mpesa_error_message')->nullable(); // To track why a push failed
        };

        Schema::table('purchases', $columns);
        Schema::table('float_requests', $columns);
        Schema::table('expenses', $columns);
    }

    public function down(): void
    {
        $columns = ['mpesa_checkout_id', 'mpesa_phone', 'mpesa_receipt_number', 'mpesa_error_message'];

        Schema::table('purchases', fn (Blueprint $table) => $table->dropColumn($columns));
        Schema::table('float_requests', fn (Blueprint $table) => $table->dropColumn($columns));
        Schema::table('expenses', fn (Blueprint $table) => $table->dropColumn($columns));
    }
};