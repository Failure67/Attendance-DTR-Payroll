<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('approval_logs', function (Blueprint $table) {
            $table->id();
            $table->string('resource_type', 100);
            $table->unsignedBigInteger('resource_id');
            $table->foreignId('actor_id')
                ->constrained('users')
                ->onDelete('cascade');
            $table->string('actor_role', 50)->nullable();
            $table->string('action', 100);
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['resource_type', 'resource_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('approval_logs');
    }
};
