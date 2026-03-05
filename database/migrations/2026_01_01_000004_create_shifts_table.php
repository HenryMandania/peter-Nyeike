<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('shifts', function (Blueprint $table) {
            $table->id();

            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->decimal('opening_balance', 15, 2);
            $table->decimal('closing_balance', 15, 2)->nullable();
            $table->decimal('system_balance', 15, 2)->default(0);
            
            // Using string instead of enum to better support the functional index logic in some SQL versions
            $table->string('status')->default('open'); 

            
            
            $table->timestamp('opened_at')->useCurrent();
            $table->timestamp('closed_at')->nullable();
            
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
        });

      
        DB::statement("
            CREATE UNIQUE INDEX unique_active_shift_per_operator 
            ON shifts (user_id, (CASE WHEN status = 'open' THEN status ELSE NULL END))
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shifts');
    }
};