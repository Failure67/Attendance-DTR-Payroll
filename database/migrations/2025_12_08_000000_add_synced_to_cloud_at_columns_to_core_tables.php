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
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('synced_to_cloud_at')->nullable();
        });

        Schema::table('attendances', function (Blueprint $table) {
            $table->timestamp('synced_to_cloud_at')->nullable();
        });

        Schema::table('payrolls', function (Blueprint $table) {
            $table->timestamp('synced_to_cloud_at')->nullable();
        });

        Schema::table('payroll_deductions', function (Blueprint $table) {
            $table->timestamp('synced_to_cloud_at')->nullable();
        });

        Schema::table('cash_advances', function (Blueprint $table) {
            $table->timestamp('synced_to_cloud_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('synced_to_cloud_at');
        });

        Schema::table('attendances', function (Blueprint $table) {
            $table->dropColumn('synced_to_cloud_at');
        });

        Schema::table('payrolls', function (Blueprint $table) {
            $table->dropColumn('synced_to_cloud_at');
        });

        Schema::table('payroll_deductions', function (Blueprint $table) {
            $table->dropColumn('synced_to_cloud_at');
        });

        Schema::table('cash_advances', function (Blueprint $table) {
            $table->dropColumn('synced_to_cloud_at');
        });
    }
};
