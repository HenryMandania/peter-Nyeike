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
        // Using raw SQL is the safest way to update ENUMs in MySQL
        \DB::statement("ALTER TABLE float_requests MODIFY COLUMN status ENUM('pending', 'approved', 'rejected', 'funded') NOT NULL DEFAULT 'pending'");
    }
    
    public function down(): void
    {
        \DB::statement("ALTER TABLE float_requests MODIFY COLUMN status ENUM('pending', 'approved', 'rejected') NOT NULL DEFAULT 'pending'");
    }
};
