<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->string('recurrence_rule')->nullable()->after('completed_at'); // daily, weekly, monthly, yearly
            $table->unsignedTinyInteger('recurrence_interval')->default(1)->after('recurrence_rule');
            $table->json('recurrence_days')->nullable()->after('recurrence_interval'); // [0..6] for weekly
            $table->date('recurrence_ends_at')->nullable()->after('recurrence_days');
            $table->foreignId('parent_task_id')->nullable()->constrained('tasks')->nullOnDelete()->after('recurrence_ends_at');
        });
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropForeign(['parent_task_id']);
            $table->dropColumn(['recurrence_rule', 'recurrence_interval', 'recurrence_days', 'recurrence_ends_at', 'parent_task_id']);
        });
    }
};
