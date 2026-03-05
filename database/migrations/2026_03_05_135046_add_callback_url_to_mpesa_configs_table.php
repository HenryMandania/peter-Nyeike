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
        // Adding the field after 'passkey' for better organization
        $table->string('callback_url')->nullable()->after('passkey');
    });
}

public function down(): void
{
    Schema::table('mpesa_configs', function (Blueprint $table) {
        $table->dropColumn('callback_url');
    });
}
};
