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
        Schema::create('cash_advances', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                ->constrained('users')
                ->onDelete('cascade');

            $table->enum('type', ['advance', 'repayment'])->default('advance');
            $table->decimal('amount', 10, 2);
            $table->string('description', 255)->nullable();
            $table->enum('source', ['admin', 'payroll'])->default('admin');

            $table->foreignId('payroll_id')
                ->nullable()
                ->constrained('payrolls')
                ->onDelete('cascade');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cash_advances');
    }
};
