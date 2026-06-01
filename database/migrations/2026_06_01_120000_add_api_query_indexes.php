<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('daily_progress_entries', function (Blueprint $table) {
            $table->index(['user_id', 'mood', 'date'], 'daily_progress_user_mood_date_idx');
            $table->index(['user_id', 'updated_at'], 'daily_progress_user_updated_idx');
        });

        Schema::table('daily_progress_entry_tag', function (Blueprint $table) {
            $table->index('daily_progress_tag_id', 'daily_progress_entry_tag_tag_idx');
        });

        Schema::table('work_logs', function (Blueprint $table) {
            $table->index(['user_id', 'category', 'date'], 'work_logs_user_category_date_idx');
            $table->index(['user_id', 'priority', 'date'], 'work_logs_user_priority_date_idx');
            $table->index(['user_id', 'project_name', 'date'], 'work_logs_user_project_name_date_idx');
            $table->index(['user_id', 'updated_at'], 'work_logs_user_updated_idx');
        });

        Schema::table('work_log_tag', function (Blueprint $table) {
            $table->index('work_log_tag_id', 'work_log_tag_tag_idx');
        });

        Schema::table('learning_entries', function (Blueprint $table) {
            $table->index(['user_id', 'source_type', 'date'], 'learning_user_source_date_idx');
            $table->index(['user_id', 'updated_at'], 'learning_user_updated_idx');
        });

        Schema::table('milestones', function (Blueprint $table) {
            $table->index(['user_id', 'category'], 'milestones_user_category_idx');
            $table->index(['user_id', 'target_type'], 'milestones_user_target_type_idx');
            $table->index(['user_id', 'end_date'], 'milestones_user_end_date_idx');
            $table->index(['user_id', 'updated_at'], 'milestones_user_updated_idx');
        });

        Schema::table('tasks', function (Blueprint $table) {
            $table->index(['user_id', 'project_id', 'status'], 'tasks_user_project_status_idx');
            $table->index(['user_id', 'priority', 'due_date'], 'tasks_user_priority_due_idx');
            $table->index(['user_id', 'updated_at'], 'tasks_user_updated_idx');
        });

        Schema::table('references', function (Blueprint $table) {
            $table->index(['user_id', 'created_at'], 'references_user_created_idx');
        });

        Schema::table('saved_views', function (Blueprint $table) {
            $table->index(['user_id', 'module', 'pinned'], 'saved_views_user_module_pinned_idx');
        });

        Schema::table('audit_logs', function (Blueprint $table) {
            $table->index(['user_id', 'created_at'], 'audit_logs_user_created_idx');
        });
    }

    public function down(): void
    {
        Schema::table('audit_logs', fn (Blueprint $table) => $table->dropIndex('audit_logs_user_created_idx'));
        Schema::table('saved_views', fn (Blueprint $table) => $table->dropIndex('saved_views_user_module_pinned_idx'));
        Schema::table('references', fn (Blueprint $table) => $table->dropIndex('references_user_created_idx'));
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropIndex('tasks_user_project_status_idx');
            $table->dropIndex('tasks_user_priority_due_idx');
            $table->dropIndex('tasks_user_updated_idx');
        });
        Schema::table('milestones', function (Blueprint $table) {
            $table->dropIndex('milestones_user_category_idx');
            $table->dropIndex('milestones_user_target_type_idx');
            $table->dropIndex('milestones_user_end_date_idx');
            $table->dropIndex('milestones_user_updated_idx');
        });
        Schema::table('learning_entries', function (Blueprint $table) {
            $table->dropIndex('learning_user_source_date_idx');
            $table->dropIndex('learning_user_updated_idx');
        });
        Schema::table('work_log_tag', fn (Blueprint $table) => $table->dropIndex('work_log_tag_tag_idx'));
        Schema::table('work_logs', function (Blueprint $table) {
            $table->dropIndex('work_logs_user_category_date_idx');
            $table->dropIndex('work_logs_user_priority_date_idx');
            $table->dropIndex('work_logs_user_project_name_date_idx');
            $table->dropIndex('work_logs_user_updated_idx');
        });
        Schema::table('daily_progress_entry_tag', fn (Blueprint $table) => $table->dropIndex('daily_progress_entry_tag_tag_idx'));
        Schema::table('daily_progress_entries', function (Blueprint $table) {
            $table->dropIndex('daily_progress_user_mood_date_idx');
            $table->dropIndex('daily_progress_user_updated_idx');
        });
    }
};
