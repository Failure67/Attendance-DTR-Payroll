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
        Schema::create('payrolls', function (Blueprint $table) {
            $table->id();
            
            $table->foreignId('user_id')
                ->constrained('users')
                ->onDelete('cascade');
            
            $table->enum('wage_type', ['Hourly', 'Daily', 'Weekly', 'Monthly']);

            $table->decimal('min_wage', 6, 2);
            $table->decimal('hours_worked', 5, 2)->nullable();
            $table->decimal('days_worked', 3, 2)->nullable();
            $table->decimal('gross_pay', 6, 2)->nullable();
            $table->decimal('deductions', 6, 2)->nullable();
            $table->decimal('net_pay', 6, 2)->nullable();

            $table->enum('status', ['Pending', 'Released', 'Cancelled'])->default('Pending');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payrolls');
    }
};
