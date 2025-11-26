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
        Schema::table('payrolls', function (Blueprint $table) {
            $table->date('period_start')->nullable()->after('status');
            $table->date('period_end')->nullable()->after('period_start');
            $table->decimal('regular_hours', 6, 2)->nullable()->after('days_worked');
            $table->decimal('overtime_hours', 6, 2)->nullable()->after('regular_hours');
            $table->decimal('absent_days', 4, 2)->nullable()->after('overtime_hours');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payrolls', function (Blueprint $table) {
            $table->dropColumn(['period_start', 'period_end', 'regular_hours', 'overtime_hours', 'absent_days']);
        });
    }
};
