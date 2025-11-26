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
        Schema::create('contribution_brackets', function (Blueprint $table) {
            $table->id();

            $table->enum('type', ['SSS', 'PhilHealth', 'Pag-IBIG']);
            $table->decimal('range_from', 10, 2);
            $table->decimal('range_to', 10, 2)->nullable();
            $table->decimal('employee_rate', 8, 6)->nullable();
            $table->decimal('employee_amount', 10, 2)->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('contribution_brackets');
    }
};
