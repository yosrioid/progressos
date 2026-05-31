<?php

use App\Models\DailyProgressEntry;
use App\Models\LearningEntry;
use App\Models\Milestone;
use App\Models\Task;
use App\Models\Project;
use App\Models\ReviewEntry;
use App\Models\User;
use App\Models\WorkLog;

it('supports daily progress CRUD', function () {
    $user = User::factory()->create();
    $this->actingAs($user)->post('/daily-progress', [
        'date' => '2026-05-30',
        'title' => 'Ship progress dashboard',
        'completed_items' => ['Built dashboard'],
        'tags' => ['shipping', 'focus'],
    ])->assertSessionHasNoErrors();

    $entry = DailyProgressEntry::first();
    expect($entry)->not->toBeNull();

    $this->actingAs($user)->put("/daily-progress/{$entry->id}", [
        'date' => '2026-05-30',
        'title' => 'Ship ProgressOS dashboard',
        'completed_items' => ['Built dashboard'],
        'tags' => ['shipping'],
    ])->assertSessionHasNoErrors();

    $this->actingAs($user)->get('/daily-progress?search=ProgressOS')->assertOk();
    $this->actingAs($user)->delete("/daily-progress/{$entry->id}")->assertRedirect('/daily-progress');
});

it('supports work log CRUD and bulk status updates', function () {
    $user = User::factory()->create();
    $payload = WorkLog::factory()->make(['user_id' => $user->id])->toArray() + ['tags' => ['backend']];

    $this->actingAs($user)->post('/work-logs', $payload)->assertSessionHasNoErrors();
    $log = WorkLog::first();

    $this->actingAs($user)->patch('/work-logs/bulk-status', ['ids' => [$log->id], 'status' => 'done'])->assertSessionHasNoErrors();
    expect($log->fresh()->status)->toBe('done');

    $this->actingAs($user)->get('/work-logs?status=done')->assertOk();
    $this->actingAs($user)->delete("/work-logs/{$log->id}")->assertRedirect('/work-logs');
});

it('supports learning and milestone CRUD', function () {
    $user = User::factory()->create();

    $learning = LearningEntry::factory()->make(['user_id' => $user->id])->toArray();
    $this->actingAs($user)->post('/learning', $learning)->assertSessionHasNoErrors();
    $this->actingAs($user)->get('/learning')->assertOk();
    $this->actingAs($user)->delete('/learning/'.LearningEntry::first()->id)->assertRedirect('/learning');

    $milestone = Milestone::factory()->make(['user_id' => $user->id])->toArray();
    $this->actingAs($user)->post('/milestones', $milestone)->assertSessionHasNoErrors();
    $this->actingAs($user)->get('/milestones')->assertOk();
    $this->actingAs($user)->delete('/milestones/'.Milestone::first()->id)->assertRedirect('/milestones');
});

it('renders dashboard and generated reports from real records', function () {
    $user = User::factory()->create();
    WorkLog::factory()->for($user)->create(['status' => 'done', 'date' => now()->toDateString(), 'actual_duration' => 90]);
    LearningEntry::factory()->for($user)->create(['date' => now()->toDateString(), 'duration_minutes' => 45]);
    DailyProgressEntry::factory()->for($user)->create(['date' => now()->toDateString(), 'completed_items' => ['Completed MVP slice']]);
    Milestone::factory()->for($user)->create(['current_value' => 5, 'target_value' => 10]);

    $this->actingAs($user)->get('/dashboard')->assertOk();
    $this->actingAs($user)->get('/reports/weekly')->assertOk();
    $this->actingAs($user)->get('/reports/weekly/export')->assertOk();
    $this->actingAs($user)->get('/search?q=MVP')->assertOk();
});

it('supports tasks, quick capture, and review pages', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post('/tasks', [
        'title' => 'Review product flow',
        'status' => 'todo',
        'priority' => 'high',
        'due_date' => now()->toDateString(),
    ])->assertSessionHasNoErrors();

    $task = Task::first();
    expect($task)->not->toBeNull();

    $this->actingAs($user)->patch("/tasks/{$task->id}/status", ['status' => 'done'])
        ->assertSessionHasNoErrors();
    expect($task->fresh()->status)->toBe('done');

    $this->actingAs($user)->post('/quick-capture', [
        'type' => 'blocker',
        'title' => 'Waiting for API credentials',
        'date' => now()->toDateString(),
    ])->assertSessionHasNoErrors();

    $this->actingAs($user)->get('/tasks')->assertOk();
    $this->actingAs($user)->get('/reviews/daily')->assertOk();
    $this->actingAs($user)->get('/reviews/weekly')->assertOk();
});

it('supports persisted reviews references saved views and project pages', function () {
    $user = User::factory()->create();
    $project = Project::create(['user_id' => $user->id, 'name' => 'ProgressOS', 'color' => 'teal']);
    $task = Task::factory()->for($user)->create(['project_id' => $project->id, 'status' => 'todo']);

    $this->actingAs($user)->post('/reviews', [
        'period_type' => 'daily',
        'period_start' => now()->toDateString(),
        'period_end' => now()->toDateString(),
        'answers' => ['moved' => 'Shipped flow'],
        'summary' => 'Good day',
    ])->assertSessionHasNoErrors();
    expect(ReviewEntry::first()->summary)->toBe('Good day');

    $this->actingAs($user)->patch("/reviews/tasks/{$task->id}/carry", [
        'due_date' => now()->addDay()->toDateString(),
    ])->assertSessionHasNoErrors();

    $this->actingAs($user)->post("/reviews/tasks/{$task->id}/work-log")
        ->assertSessionHasNoErrors();
    expect($task->fresh()->work_log_id)->not->toBeNull();

    $this->actingAs($user)->post('/references', [
        'referenceable_type' => 'task',
        'referenceable_id' => $task->id,
        'label' => 'Spec',
        'url' => 'https://example.com/spec',
        'type' => 'doc',
    ])->assertSessionHasNoErrors();
    expect($task->fresh()->references()->count())->toBe(1);

    $this->actingAs($user)->post('/saved-views', [
        'module' => 'tasks',
        'name' => 'Blocked',
        'filters' => ['status' => 'blocked'],
        'pinned' => true,
    ])->assertSessionHasNoErrors();

    $this->actingAs($user)->get('/projects')->assertOk();
    $this->actingAs($user)->get("/projects/{$project->id}")->assertOk();

    $this->actingAs($user)->post("/projects/{$project->id}/tasks", [
        'title' => 'Project scoped task',
        'status' => 'todo',
        'priority' => 'medium',
        'due_date' => now()->toDateString(),
    ])->assertSessionHasNoErrors();
    expect($project->tasks()->where('title', 'Project scoped task')->exists())->toBeTrue();

    $this->actingAs($user)->post("/projects/{$project->id}/work-logs", [
        'date' => now()->toDateString(),
        'title' => 'Project scoped log',
        'category' => 'feature',
        'actual_duration' => 30,
        'description' => 'Logged from project workspace.',
    ])->assertSessionHasNoErrors();
    expect($project->workLogs()->where('title', 'Project scoped log')->exists())->toBeTrue();

    $this->actingAs($user)->patch("/projects/{$project->id}", [
        'name' => 'ProgressOS Core',
        'archived' => false,
    ])->assertSessionHasNoErrors();
    expect($project->fresh()->name)->toBe('ProgressOS Core');
});
