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
        Schema::table('overtime_entries', function (Blueprint $table) {
            $table->timestamp('supervisor_approved_at')->nullable()->after('approved_at');
            $table->timestamp('manager_approved_at')->nullable()->after('supervisor_approved_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('overtime_entries', function (Blueprint $table) {
            $table->dropColumn(['supervisor_approved_at', 'manager_approved_at']);
        });
    }
};
