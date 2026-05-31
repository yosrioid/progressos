<?php

use App\Models\DailyProgressEntry;
use App\Models\LearningEntry;
use App\Models\Milestone;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Models\WorkLog;

it('supports daily progress creation and listing through the API', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->postJson('/api/v1/daily-progress', [
        'date' => '2026-05-30',
        'title' => 'Ship ProgressOS dashboard',
        'completed_items' => ['Built dashboard'],
        'tags' => ['shipping', 'focus'],
    ])->assertCreated()->assertJsonPath('entry.title', 'Ship ProgressOS dashboard');

    expect(DailyProgressEntry::query()->where('title', 'Ship ProgressOS dashboard')->exists())->toBeTrue();

    $this->actingAs($user)->getJson('/api/v1/daily-progress')
        ->assertOk()
        ->assertJsonPath('entries.data.0.title', 'Ship ProgressOS dashboard');
});

it('supports work log creation, listing, and project resolution through the API', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->postJson('/api/v1/work-logs', [
        'date' => '2026-05-30',
        'project_name' => 'ProgressOS',
        'title' => 'Build Vue REST shell',
        'category' => 'feature',
        'status' => 'done',
        'priority' => 'high',
        'description' => 'Replaced the previous UI with a Vue client.',
        'actual_duration' => 90,
        'tags' => ['frontend'],
    ])->assertCreated()->assertJsonPath('log.project_name', 'ProgressOS');

    expect(Project::query()->where('name', 'ProgressOS')->exists())->toBeTrue();

    $this->actingAs($user)->getJson('/api/v1/work-logs')
        ->assertOk()
        ->assertJsonPath('logs.data.0.title', 'Build Vue REST shell');
});

it('supports learning entries and milestones through the API', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->postJson('/api/v1/learning', [
        'date' => '2026-05-30',
        'topic' => 'Vue Composition API',
        'category' => 'programming',
        'source_type' => 'practice',
        'duration_minutes' => 45,
        'progress_notes' => 'Converted pages to Vue.',
    ])->assertCreated()->assertJsonPath('entry.topic', 'Vue Composition API');

    $this->actingAs($user)->postJson('/api/v1/milestones', [
        'title' => 'Ship v1 API client',
        'category' => 'product',
        'target_type' => 'count',
        'target_value' => 10,
        'current_value' => 2,
        'start_date' => '2026-05-01',
        'end_date' => '2026-06-30',
        'status' => 'active',
    ])->assertCreated()->assertJsonPath('milestone.title', 'Ship v1 API client');

    $this->actingAs($user)->getJson('/api/v1/learning')->assertOk();
    $this->actingAs($user)->getJson('/api/v1/milestones')->assertOk();
});

it('renders dashboard, reports, CSV export, and search from real records', function () {
    $user = User::factory()->create();
    WorkLog::factory()->for($user)->create(['status' => 'done', 'date' => now()->toDateString(), 'actual_duration' => 90, 'title' => 'MVP implementation']);
    LearningEntry::factory()->for($user)->create(['date' => now()->toDateString(), 'duration_minutes' => 45]);
    DailyProgressEntry::factory()->for($user)->create(['date' => now()->toDateString(), 'title' => 'MVP daily note', 'completed_items' => ['Completed MVP slice']]);
    Milestone::factory()->for($user)->create(['title' => 'MVP target', 'current_value' => 5, 'target_value' => 10]);

    $this->actingAs($user)->getJson('/api/v1/dashboard')->assertOk()->assertJsonStructure(['today', 'summary']);
    $this->actingAs($user)->getJson('/api/v1/reports/weekly')->assertOk()->assertJsonStructure(['report']);
    $this->actingAs($user)->get('/api/v1/reports/weekly/export')->assertOk();
    $this->actingAs($user)->getJson('/api/v1/search?q=MVP')->assertOk()->assertJsonPath('query', 'MVP');
});

it('supports tasks, quick capture, and project detail data through the API', function () {
    $user = User::factory()->create();
    $project = Project::create(['user_id' => $user->id, 'name' => 'ABC', 'color' => 'teal']);

    $this->actingAs($user)->postJson('/api/v1/tasks', [
        'title' => 'Review product flow',
        'project_id' => $project->id,
        'status' => 'todo',
        'priority' => 'high',
        'due_date' => now()->toDateString(),
    ])->assertCreated();

    $task = Task::query()->firstOrFail();

    $this->actingAs($user)->patchJson("/api/v1/tasks/{$task->id}/status", ['status' => 'done'])
        ->assertOk()
        ->assertJsonPath('task.status', 'done');
    expect($task->fresh()->completed_at)->not->toBeNull();

    $this->actingAs($user)->postJson('/api/v1/quick-capture', [
        'type' => 'work_log',
        'title' => 'Log today for ABC',
        'project_name' => 'ABC',
        'date' => now()->toDateString(),
        'duration_minutes' => 35,
    ])->assertCreated();

    $this->actingAs($user)->getJson('/api/v1/tasks')->assertOk();
    $this->actingAs($user)->getJson('/api/v1/projects')->assertOk();
    $this->actingAs($user)->getJson("/api/v1/projects/{$project->id}")->assertOk()->assertJsonPath('project.name', 'ABC');
});
