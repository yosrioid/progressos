<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('goals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('period_label')->nullable(); // e.g. "Q2 2026", "2026"
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->string('status')->default('active'); // draft, active, completed, abandoned
            $table->string('color')->default('#8b5cf6');
            $table->timestamps();
            $table->index(['user_id', 'status']);
        });

        Schema::create('key_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('goal_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->string('metric_type')->default('percentage'); // percentage, number, boolean
            $table->decimal('current_value', 10, 2)->default(0);
            $table->decimal('target_value', 10, 2)->default(100);
            $table->string('unit')->nullable(); // %, hrs, tasks, etc.
            $table->text('notes')->nullable();
            $table->string('status')->default('active'); // active, done, abandoned
            $table->unsignedSmallInteger('order')->default(0);
            $table->timestamps();
            $table->index(['goal_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('key_results');
        Schema::dropIfExists('goals');
    }
};
