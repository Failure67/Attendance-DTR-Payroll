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
        Schema::create('cash_advance_requests', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                ->constrained('users')
                ->onDelete('cascade');

            $table->decimal('amount', 10, 2);
            $table->text('reason')->nullable();

            $table->enum('status', [
                'Pending',
                'HR approved',
                'Manager approved',
                'Released',
                'Rejected',
                'Cancelled',
            ])->default('Pending');

            $table->timestamp('hr_approved_at')->nullable();
            $table->timestamp('manager_approved_at')->nullable();
            $table->timestamp('released_at')->nullable();
            $table->timestamp('rejected_at')->nullable();

            $table->string('rejection_reason', 255)->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cash_advance_requests');
    }
};
