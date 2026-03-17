<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // We update the enum to include 'funded'
        Schema::table('purchases', function (Blueprint $table) {
            $table->enum('status', ['pending', 'approved', 'rejected', 'funded'])
                  ->default('pending')
                  ->change();
        });
    }

    public function down(): void
    {
        // To rollback, we revert to the original list
        Schema::table('purchases', function (Blueprint $table) {
            $table->enum('status', ['pending', 'approved', 'rejected'])
                  ->default('pending')
                  ->change();
        });
    }
};