<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
// In the migration file
public function up(): void
{
    Schema::create('audit_logs', function (Blueprint $table) {
        $table->id();
        $table->string('auditable_type'); // Model class
        $table->unsignedBigInteger('auditable_id');
        $table->string('event'); // created, updated, deleted
        $table->json('old_values')->nullable();
        $table->json('new_values')->nullable();
        $table->foreignId('user_id')->nullable()->constrained();
        $table->timestamps();
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
