<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('remittance_batches', function (Blueprint $table) {
            $table->id();
            $table->string('agency', 20);
            $table->date('period_month');
            $table->string('status', 20)->default('draft');
            $table->decimal('employee_total', 12, 2)->default(0);
            $table->decimal('employer_total', 12, 2)->default(0);
            $table->decimal('grand_total', 12, 2)->default(0);
            $table->string('payment_reference', 100)->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->string('proof_path', 255)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['agency', 'period_month'], 'remit_batch_agency_month_uq');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('remittance_batches');
    }
};
