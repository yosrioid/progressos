<?php

use App\Models\Task;
use App\Models\User;

it('adds security headers to web responses', function () {
    $this->get('/login')
        ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
});

it('blocks unsafe reference urls and allows public http urls', function () {
    $user = User::factory()->create();
    $task = Task::factory()->for($user)->create();

    $this->actingAs($user)->post('/references', [
        'referenceable_type' => 'task',
        'referenceable_id' => $task->id,
        'label' => 'Local',
        'url' => 'http://127.0.0.1/internal',
        'type' => 'link',
    ])->assertSessionHasErrors('url');

    $this->actingAs($user)->post('/references', [
        'referenceable_type' => 'task',
        'referenceable_id' => $task->id,
        'label' => 'Docs',
        'url' => 'https://docs.example.org/spec',
        'type' => 'doc',
    ])->assertSessionHasNoErrors();
});

it('soft deletes core records', function () {
    $user = User::factory()->create();
    $task = Task::factory()->for($user)->create();

    $this->actingAs($user)->delete("/tasks/{$task->id}")->assertRedirect('/tasks');

    expect(Task::withTrashed()->find($task->id)?->trashed())->toBeTrue();
});

it('escapes csv formula cells in report exports', function () {
    $user = User::factory()->create();
    $user->workLogs()->create([
        'date' => now()->toDateString(),
        'project_name' => '=cmd',
        'title' => 'CSV hardening',
        'category' => '=danger',
        'status' => 'done',
        'priority' => 'medium',
        'actual_duration' => 30,
    ]);

    $response = $this->actingAs($user)->get('/reports/weekly/export')
        ->assertOk();

    expect($response->streamedContent())->toContain("'=danger");
});
