<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('daily_progress_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->string('title');
            $table->text('in_progress')->nullable();
            $table->text('todo')->nullable();
            $table->text('blockers')->nullable();
            $table->text('notes')->nullable();
            $table->json('completed_items')->nullable();
            $table->string('mood')->nullable();
            $table->boolean('archived')->default(false);
            $table->timestamps();
            $table->index(['user_id', 'date']);
            $table->index(['user_id', 'archived']);
        });

        Schema::create('daily_progress_tags', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->timestamps();
            $table->unique(['user_id', 'name']);
        });

        Schema::create('daily_progress_entry_tag', function (Blueprint $table) {
            $table->foreignId('daily_progress_entry_id')->constrained()->cascadeOnDelete();
            $table->foreignId('daily_progress_tag_id')->constrained()->cascadeOnDelete();
            $table->primary(['daily_progress_entry_id', 'daily_progress_tag_id'], 'daily_progress_tag_entry_pk');
        });

        Schema::create('work_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->string('project_name')->index();
            $table->string('ticket_code')->nullable()->index();
            $table->string('title');
            $table->string('category')->index();
            $table->string('status')->index();
            $table->string('priority')->default('medium')->index();
            $table->text('description')->nullable();
            $table->text('resolution_or_outcome')->nullable();
            $table->unsignedInteger('estimated_duration')->nullable();
            $table->unsignedInteger('actual_duration')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'date']);
            $table->index(['user_id', 'status', 'date']);
        });

        Schema::create('work_log_tags', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->timestamps();
            $table->unique(['user_id', 'name']);
        });

        Schema::create('work_log_tag', function (Blueprint $table) {
            $table->foreignId('work_log_id')->constrained()->cascadeOnDelete();
            $table->foreignId('work_log_tag_id')->constrained()->cascadeOnDelete();
            $table->primary(['work_log_id', 'work_log_tag_id']);
        });

        Schema::create('learning_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('date')->index();
            $table->string('topic');
            $table->string('category')->index();
            $table->string('source_type')->index();
            $table->unsignedInteger('duration_minutes');
            $table->text('progress_notes')->nullable();
            $table->text('takeaway')->nullable();
            $table->text('next_action')->nullable();
            $table->unsignedTinyInteger('rating')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'date']);
            $table->index(['user_id', 'category', 'date']);
        });

        Schema::create('milestones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->string('category')->index();
            $table->string('target_type')->index();
            $table->decimal('target_value', 10, 2);
            $table->decimal('current_value', 10, 2)->default(0);
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable()->index();
            $table->string('status')->default('active')->index();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'status']);
        });

        Schema::create('report_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('period_type');
            $table->date('period_start');
            $table->date('period_end');
            $table->json('payload');
            $table->timestamps();
            $table->unique(['user_id', 'period_type', 'period_start']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('report_snapshots');
        Schema::dropIfExists('milestones');
        Schema::dropIfExists('learning_entries');
        Schema::dropIfExists('work_log_tag');
        Schema::dropIfExists('work_log_tags');
        Schema::dropIfExists('work_logs');
        Schema::dropIfExists('daily_progress_entry_tag');
        Schema::dropIfExists('daily_progress_tags');
        Schema::dropIfExists('daily_progress_entries');
    }
};
