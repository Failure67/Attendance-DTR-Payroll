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
        // Extend wage_type enum to support Piece rate
        DB::statement("ALTER TABLE payrolls MODIFY COLUMN wage_type ENUM('Hourly','Daily','Weekly','Monthly','Piece rate') NOT NULL");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revert to original enum without Piece rate
        DB::statement("ALTER TABLE payrolls MODIFY COLUMN wage_type ENUM('Hourly','Daily','Weekly','Monthly') NOT NULL");
    }
};
