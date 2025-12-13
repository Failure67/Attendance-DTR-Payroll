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
            $table->unsignedBigInteger('hr_approved_by')->nullable()->after('status');
            $table->timestamp('hr_approved_at')->nullable()->after('hr_approved_by');

            $table->unsignedBigInteger('admin_approved_by')->nullable()->after('hr_approved_at');
            $table->timestamp('admin_approved_at')->nullable()->after('admin_approved_by');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payrolls', function (Blueprint $table) {
            $table->dropColumn([
                'hr_approved_by',
                'hr_approved_at',
                'admin_approved_by',
                'admin_approved_at',
            ]);
        });
    }
};
