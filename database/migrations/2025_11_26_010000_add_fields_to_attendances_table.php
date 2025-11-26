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
        Schema::table('attendances', function (Blueprint $table) {
            $table->date('date')->nullable()->after('user_id');
            $table->decimal('total_hours', 5, 2)->default(0)->after('time_out');
            $table->decimal('overtime_hours', 5, 2)->default(0)->after('total_hours');
            $table->enum('status', ['Present', 'Absent', 'Late', 'On leave'])->default('Present')->after('overtime_hours');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('attendances', function (Blueprint $table) {
            $table->dropColumn(['date', 'total_hours', 'overtime_hours', 'status']);
        });
    }
};
