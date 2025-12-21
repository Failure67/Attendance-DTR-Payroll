<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('remittance_line_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('batch_id')->constrained('remittance_batches')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('employee_name', 255);
            $table->string('membership_number', 50)->nullable();
            $table->decimal('employee_amount', 12, 2)->default(0);
            $table->decimal('employer_amount', 12, 2)->default(0);
            $table->decimal('total_amount', 12, 2)->default(0);
            $table->boolean('missing_membership')->default(false);
            $table->timestamps();

            $table->index(['batch_id', 'user_id'], 'remit_li_batch_user_ix');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('remittance_line_items');
    }
};
