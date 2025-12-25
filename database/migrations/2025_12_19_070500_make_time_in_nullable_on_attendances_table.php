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
        // Allow null time_in so we can safely store pure status days
        // such as On leave / Absent without fake timestamps.
        DB::statement('ALTER TABLE `attendances` MODIFY `time_in` DATETIME NULL');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revert to NOT NULL time_in (you may need to ensure all rows
        // have a value before running down on this migration).
        DB::statement('ALTER TABLE `attendances` MODIFY `time_in` DATETIME NOT NULL');
    }
};
