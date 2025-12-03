<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // 1) Expand enum to cover all allowed roles (including legacy values)
        Schema::table('users', function (Blueprint $table) {
            $table->enum('role', [
                'admin',
                'worker',
                'Superadmin',
                'HR Manager',
                'Payroll Officer',
                'Supervisor',
            ])->default('Worker')->change();
        });

        // 2) Normalize existing roles into the new naming scheme
        DB::table('users')->where('role', 'admin')->update(['role' => 'Superadmin']);
        DB::table('users')->where('role', 'worker')->update(['role' => 'Worker']);
    }

    public function down(): void
    {
        // 1) Normalize roles back to the original lowercase values
        DB::table('users')->where('role', 'Superadmin')->update(['role' => 'admin']);
        DB::table('users')->where('role', 'Admin')->update(['role' => 'admin']);
        DB::table('users')->where('role', 'Worker')->update(['role' => 'worker']);
        DB::table('users')->whereNotIn('role', ['admin', 'worker'])->update(['role' => 'worker']);

        // 2) Collapse enum back to the original definition
        Schema::table('users', function (Blueprint $table) {
            $table->enum('role', ['admin', 'worker'])->default('worker')->change();
        });
    }
};
