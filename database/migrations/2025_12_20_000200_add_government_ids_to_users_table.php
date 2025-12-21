<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('user_credentials', 'sss_number')) {
            Schema::table('user_credentials', function (Blueprint $table) {
                $table->string('sss_number', 30)->nullable()->after('gender');
            });
        }

        if (!Schema::hasColumn('user_credentials', 'philhealth_number')) {
            Schema::table('user_credentials', function (Blueprint $table) {
                $table->string('philhealth_number', 30)->nullable()->after('sss_number');
            });
        }

        if (!Schema::hasColumn('user_credentials', 'pagibig_number')) {
            Schema::table('user_credentials', function (Blueprint $table) {
                $table->string('pagibig_number', 30)->nullable()->after('philhealth_number');
            });
        }
    }

    public function down(): void
    {
        $columns = [];

        if (Schema::hasColumn('user_credentials', 'pagibig_number')) {
            $columns[] = 'pagibig_number';
        }

        if (Schema::hasColumn('user_credentials', 'philhealth_number')) {
            $columns[] = 'philhealth_number';
        }

        if (Schema::hasColumn('user_credentials', 'sss_number')) {
            $columns[] = 'sss_number';
        }

        if (!empty($columns)) {
            Schema::table('user_credentials', function (Blueprint $table) use ($columns) {
                $table->dropColumn($columns);
            });
        }
    }
};
