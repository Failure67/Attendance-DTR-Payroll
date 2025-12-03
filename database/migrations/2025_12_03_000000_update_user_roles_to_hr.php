<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // 1) Ensure the enum includes the new HR role alongside existing mixed-case roles
        Schema::table('users', function (Blueprint $table) {
            $table->enum('role', [
                'Superadmin',
                'Admin',
                'HR Manager',
                'Payroll Officer',
                'HR',
                'Supervisor',
                'Worker',
            ])->default('Worker')->change();
        });

        // 2) Merge legacy HR Manager + Payroll Officer into the new HR role
        DB::table('users')
            ->whereIn('role', ['HR Manager', 'Payroll Officer'])
            ->update(['role' => 'HR']);

        // 3) Tighten enum to the final, simplified role set
        Schema::table('users', function (Blueprint $table) {
            $table->enum('role', [
                'Superadmin',
                'Admin',
                'HR',
                'Supervisor',
                'Worker',
            ])->default('Worker')->change();
        });
    }

    public function down(): void
    {
        // 1) Allow legacy HR Manager/Payroll Officer again together with HR
        Schema::table('users', function (Blueprint $table) {
            $table->enum('role', [
                'Superadmin',
                'Admin',
                'HR Manager',
                'Payroll Officer',
                'HR',
                'Supervisor',
                'Worker',
            ])->default('Worker')->change();
        });

        // 2) Map HR back to HR Manager for compatibility
        DB::table('users')
            ->where('role', 'HR')
            ->update(['role' => 'HR Manager']);
    }
};
