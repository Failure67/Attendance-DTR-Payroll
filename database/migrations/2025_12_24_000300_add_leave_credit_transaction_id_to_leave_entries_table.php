<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leave_entries', function (Blueprint $table) {
            $table->foreignId('leave_credit_transaction_id')
                ->nullable()
                ->after('paid_amount')
                ->constrained('leave_credit_transactions')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('leave_entries', function (Blueprint $table) {
            $table->dropConstrainedForeignId('leave_credit_transaction_id');
        });
    }
};
