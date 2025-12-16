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
        Schema::table('cash_advance_requests', function (Blueprint $table) {
            $table->timestamp('synced_to_cloud_at')->nullable();
        });

        Schema::table('crew_assignments', function (Blueprint $table) {
            $table->timestamp('synced_to_cloud_at')->nullable();
        });

        Schema::table('activity_logs', function (Blueprint $table) {
            $table->timestamp('synced_to_cloud_at')->nullable();
        });

        Schema::table('announcements', function (Blueprint $table) {
            $table->timestamp('synced_to_cloud_at')->nullable();
        });

        Schema::table('user_credentials', function (Blueprint $table) {
            $table->timestamp('synced_to_cloud_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cash_advance_requests', function (Blueprint $table) {
            $table->dropColumn('synced_to_cloud_at');
        });

        Schema::table('crew_assignments', function (Blueprint $table) {
            $table->dropColumn('synced_to_cloud_at');
        });

        Schema::table('activity_logs', function (Blueprint $table) {
            $table->dropColumn('synced_to_cloud_at');
        });

        Schema::table('announcements', function (Blueprint $table) {
            $table->dropColumn('synced_to_cloud_at');
        });

        Schema::table('user_credentials', function (Blueprint $table) {
            $table->dropColumn('synced_to_cloud_at');
        });
    }
};
