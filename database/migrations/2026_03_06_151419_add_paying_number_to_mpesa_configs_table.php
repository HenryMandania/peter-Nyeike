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
        Schema::table('mpesa_configs', function (Blueprint $table) {
            $table->string('paying_number')->after('shortcode'); // Ensure this is 2547XXXXXXXX
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('mpesa_configs', function (Blueprint $table) {
            $table->dropColumn('paying_number');
        });
    }
};