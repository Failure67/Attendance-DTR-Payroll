<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leave_credit_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained('leave_credit_accounts')->onDelete('cascade');
            $table->enum('direction', ['credit', 'debit']);
            $table->decimal('amount', 8, 3);
            $table->decimal('remaining_amount', 8, 3)->nullable();
            $table->timestamp('occurred_at');
            $table->date('effective_date');
            $table->string('type', 50);
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('reference_type', 50)->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->string('description', 255)->nullable();
            $table->date('expires_at')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['account_id', 'occurred_at']);
            $table->index(['account_id', 'effective_date']);
            $table->index(['reference_type', 'reference_id']);
            $table->unique(['account_id', 'type', 'reference_type', 'reference_id'], 'lct_acc_type_ref_uq');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leave_credit_transactions');
    }
};
