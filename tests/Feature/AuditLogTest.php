<?php

use App\Models\Task;
use App\Models\User;

it('logs the created event for an auditable task', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->postJson('/api/v1/tasks', [
        'title' => 'Auditable task',
        'status' => 'todo',
        'priority' => 'medium',
    ]);
    $response->assertCreated();

    $taskId = Task::query()->where('title', 'Auditable task')->value('id');

    $auditLog = $user->auditLogs()->where('event', 'Task.created')->first();
    expect($auditLog)->not->toBeNull();
    expect($auditLog->auditable_type)->toBe(Task::class);
    expect($auditLog->auditable_id)->toBe($taskId);
});

it('logs the updated event with before and after metadata', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->postJson('/api/v1/tasks', [
        'title' => 'Original task',
        'status' => 'todo',
        'priority' => 'medium',
    ])->assertCreated();

    $taskId = Task::query()->where('title', 'Original task')->value('id');

    $this->actingAs($user)->patchJson("/api/v1/tasks/{$taskId}", [
        'title' => 'Updated task',
        'status' => 'todo',
        'priority' => 'medium',
    ])->assertOk();

    $auditLog = $user->auditLogs()->where('event', 'Task.updated')->first();
    expect($auditLog)->not->toBeNull();
    expect($auditLog->metadata['before']['title'])->toBe('Original task');
    expect($auditLog->metadata['after']['title'])->toBe('Updated task');
});

it('logs the deleted event with a full snapshot', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->postJson('/api/v1/tasks', [
        'title' => 'To be deleted',
        'status' => 'todo',
        'priority' => 'medium',
    ])->assertCreated();

    $taskId = Task::query()->where('title', 'To be deleted')->value('id');

    $this->actingAs($user)->deleteJson("/api/v1/tasks/{$taskId}")->assertNoContent();

    $auditLog = $user->auditLogs()->where('event', 'Task.deleted')->first();
    expect($auditLog)->not->toBeNull();
    expect($auditLog->auditable_id)->toBe($taskId);
    expect($auditLog->metadata['snapshot'])->toHaveKey('title');
    expect($auditLog->metadata['snapshot']['title'])->toBe('To be deleted');
});

it('records metadata for the user that performed the change', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->postJson('/api/v1/tasks', [
        'title' => 'Owner check',
        'status' => 'todo',
        'priority' => 'medium',
    ])->assertCreated();

    $auditLog = $user->auditLogs()->where('event', 'Task.created')->first();
    expect($auditLog->user_id)->toBe($user->id);
    expect($auditLog->ip_address)->not->toBeNull();
    expect($auditLog->user_agent)->not->toBeNull();
});
