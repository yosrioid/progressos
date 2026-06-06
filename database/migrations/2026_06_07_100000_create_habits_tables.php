<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('habits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('color')->default('#3b82f6');
            $table->string('icon')->default('✓');
            $table->string('frequency')->default('daily'); // daily, weekly
            $table->json('target_days')->nullable(); // [0..6] for weekly habits
            $table->boolean('active')->default(true);
            $table->boolean('archived')->default(false);
            $table->unsignedSmallInteger('order')->default(0);
            $table->timestamps();
            $table->index(['user_id', 'active']);
        });

        Schema::create('habit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('habit_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->unique(['habit_id', 'date']);
            $table->index(['user_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('habit_logs');
        Schema::dropIfExists('habits');
    }
};
