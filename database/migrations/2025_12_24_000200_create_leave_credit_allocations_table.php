<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leave_credit_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('debit_transaction_id')->constrained('leave_credit_transactions')->onDelete('cascade');
            $table->foreignId('credit_transaction_id')->constrained('leave_credit_transactions')->onDelete('cascade');
            $table->decimal('amount', 8, 3);
            $table->timestamps();

            $table->unique(['debit_transaction_id', 'credit_transaction_id'], 'lca_debit_credit_uq');
            $table->index(['credit_transaction_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leave_credit_allocations');
    }
};
