<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('game_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type', 32)->default('sudoku');
            $table->string('level', 16);
            $table->json('puzzle');
            $table->json('solution');
            $table->json('user_state')->nullable();
            $table->json('notes_state')->nullable();
            $table->unsignedInteger('elapsed_seconds')->default(0);
            $table->string('status', 16)->default('active');
            $table->timestamps();

            $table->index(['user_id', 'status']);
        });

        Schema::create('game_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type', 32)->default('sudoku');
            $table->string('level', 16);
            $table->unsignedInteger('duration_seconds');
            $table->timestamp('completed_at');
            $table->timestamps();

            $table->index(['user_id', 'type', 'level', 'duration_seconds']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('game_records');
        Schema::dropIfExists('game_sessions');
    }
};
