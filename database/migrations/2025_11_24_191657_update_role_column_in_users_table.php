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
        // First, make sure the role column exists
        if (!Schema::hasColumn('users', 'role')) {
            Schema::table('users', function (Blueprint $table) {
                $table->enum('role', ['admin', 'worker'])->default('worker')->after('password');
            });
        } else {
            // If the column exists, modify it to use enum
            Schema::table('users', function (Blueprint $table) {
                $table->enum('role', ['admin', 'worker'])->default('worker')->change();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revert to string if needed
        Schema::table('users', function (Blueprint $table) {
            $table->string('role')->nullable()->change();
        });
    }
};
