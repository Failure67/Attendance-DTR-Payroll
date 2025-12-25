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
        Schema::create('leave_entries', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                ->constrained('users')
                ->onDelete('cascade');

            $table->date('date_start');
            $table->date('date_end');
            $table->decimal('duration_days', 5, 2);

            $table->string('type', 50);
            $table->boolean('is_paid')->default(true);

            $table->enum('status', ['pending', 'approved', 'rejected', 'cancelled'])->default('pending');

            $table->foreignId('requested_by_id')
                ->constrained('users')
                ->onDelete('cascade');

            $table->foreignId('approved_by_id')
                ->nullable()
                ->constrained('users')
                ->onDelete('cascade');

            $table->timestamp('approved_at')->nullable();
            $table->string('reason', 255)->nullable();
            $table->json('meta')->nullable();

            $table->foreignId('payroll_id')
                ->nullable()
                ->constrained('payrolls')
                ->onDelete('cascade');

            $table->decimal('paid_amount', 10, 2)->nullable();

            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('leave_entries');
    }
};
