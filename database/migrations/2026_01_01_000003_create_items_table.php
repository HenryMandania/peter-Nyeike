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
        Schema::create('items', function (Blueprint $table) {
            $table->id();
        
            $table->string('name');
            $table->enum('unit', ['PCs', 'KGs', 'Sacks']);
            $table->enum('department', ['Avocado', 'Timber']);       
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();        
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('items');
    }
};
