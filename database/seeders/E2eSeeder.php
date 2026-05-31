<?php

namespace Database\Seeders;

use App\Models\DailyProgressEntry;
use App\Models\LearningEntry;
use App\Models\Milestone;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Models\WorkLog;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class E2eSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::query()->updateOrCreate(
            ['email' => 'e2e@example.com'],
            [
                'name' => 'E2E User',
                'password' => Hash::make('password'),
                'timezone' => 'Asia/Jakarta',
                'theme' => 'system',
            ],
        );

        $project = Project::query()->updateOrCreate(
            ['user_id' => $user->id, 'name' => 'ABC'],
            ['color' => '#0f766e', 'archived' => false],
        );

        Task::query()->updateOrCreate(
            ['user_id' => $user->id, 'title' => 'E2E baseline task'],
            [
                'project_id' => $project->id,
                'status' => 'todo',
                'priority' => 'medium',
                'due_date' => now('Asia/Jakarta')->toDateString(),
                'notes' => 'Seeded task for UI verification.',
            ],
        );

        WorkLog::query()->updateOrCreate(
            ['user_id' => $user->id, 'title' => 'E2E baseline work log'],
            [
                'project_id' => $project->id,
                'project_name' => 'ABC',
                'date' => now('Asia/Jakarta')->toDateString(),
                'category' => 'feature',
                'status' => 'done',
                'priority' => 'medium',
                'description' => 'Seeded work log for dashboard and responsive tables.',
                'actual_duration' => 45,
            ],
        );

        DailyProgressEntry::query()->updateOrCreate(
            ['user_id' => $user->id, 'title' => 'E2E baseline progress'],
            [
                'date' => now('Asia/Jakarta')->toDateString(),
                'notes' => 'Seeded daily progress entry.',
                'completed_items' => ['Baseline data ready'],
                'mood' => 'focused',
            ],
        );

        LearningEntry::query()->updateOrCreate(
            ['user_id' => $user->id, 'topic' => 'E2E baseline learning'],
            [
                'date' => now('Asia/Jakarta')->toDateString(),
                'category' => 'programming',
                'source_type' => 'practice',
                'duration_minutes' => 30,
                'progress_notes' => 'Seeded learning entry.',
                'takeaway' => 'E2E data is stable.',
                'rating' => 4,
            ],
        );

        Milestone::query()->updateOrCreate(
            ['user_id' => $user->id, 'title' => 'E2E baseline milestone'],
            [
                'category' => 'ABC',
                'target_type' => 'count',
                'target_value' => 10,
                'current_value' => 3,
                'start_date' => now('Asia/Jakarta')->subDays(3)->toDateString(),
                'end_date' => now('Asia/Jakarta')->addWeeks(2)->toDateString(),
                'status' => 'active',
                'notes' => 'Seeded milestone for dashboard progress.',
            ],
        );
    }
}
