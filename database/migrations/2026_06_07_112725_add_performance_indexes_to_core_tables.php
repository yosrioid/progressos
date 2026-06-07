<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Composite index for task status queries (dashboard focus, open task counts)
        Schema::table('tasks', function (Blueprint $table) {
            $table->index(['user_id', 'status', 'completed_at'], 'tasks_user_status_completed_idx');
        });

        // Date-range indexes used by activity heatmap GROUP BY queries and streak queries
        Schema::table('work_logs', function (Blueprint $table) {
            $table->index(['user_id', 'date'], 'work_logs_user_date_idx');
        });

        Schema::table('learning_entries', function (Blueprint $table) {
            $table->index(['user_id', 'date'], 'learning_entries_user_date_idx');
        });

        Schema::table('daily_progress_entries', function (Blueprint $table) {
            $table->index(['user_id', 'date'], 'daily_progress_entries_user_date_idx');
        });
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropIndex('tasks_user_status_completed_idx');
        });

        Schema::table('work_logs', function (Blueprint $table) {
            $table->dropIndex('work_logs_user_date_idx');
        });

        Schema::table('learning_entries', function (Blueprint $table) {
            $table->dropIndex('learning_entries_user_date_idx');
        });

        Schema::table('daily_progress_entries', function (Blueprint $table) {
            $table->dropIndex('daily_progress_entries_user_date_idx');
        });
    }
};
