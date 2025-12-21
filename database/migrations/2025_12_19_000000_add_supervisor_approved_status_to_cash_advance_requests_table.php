<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Extend status enum to support Supervisor/Manager stages
        DB::statement("ALTER TABLE cash_advance_requests MODIFY COLUMN status ENUM('Pending','Supervisor approved','Manager approved','HR approved','Released','Rejected','Cancelled') NOT NULL DEFAULT 'Pending'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revert to original enum without Supervisor approved
        DB::statement("ALTER TABLE cash_advance_requests MODIFY COLUMN status ENUM('Pending','HR approved','Manager approved','Released','Rejected','Cancelled') NOT NULL DEFAULT 'Pending'");
    }
};
