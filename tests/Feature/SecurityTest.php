<?php

use App\Models\Task;
use App\Models\User;
use App\Models\WorkLog;
use App\Rules\SafeHttpUrl;
use Illuminate\Support\Facades\Validator;

it('adds security headers to SPA responses', function () {
    $this->get('/login')
        ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
});

it('blocks unsafe reference urls and allows public http urls', function () {
    $unsafe = Validator::make(['url' => 'http://127.0.0.1/internal'], ['url' => [new SafeHttpUrl]]);
    $safe = Validator::make(['url' => 'https://docs.example.org/spec'], ['url' => [new SafeHttpUrl]]);

    expect($unsafe->fails())->toBeTrue()
        ->and($safe->passes())->toBeTrue();
});

it('soft deletes tasks through the API', function () {
    $user = User::factory()->create();
    $task = Task::factory()->for($user)->create();

    $this->actingAs($user)->deleteJson("/api/v1/tasks/{$task->id}")->assertNoContent();

    expect(Task::withTrashed()->find($task->id)?->trashed())->toBeTrue();
});

it('escapes csv formula cells in report exports', function () {
    $user = User::factory()->create();
    $date = now()->startOfWeek()->toDateString();
    $user->workLogs()->create([
        'date' => $date,
        'project_name' => '=cmd',
        'title' => 'CSV hardening',
        'category' => '=danger',
        'status' => 'done',
        'priority' => 'medium',
        'actual_duration' => 30,
    ]);

    $response = $this->actingAs($user)->get("/api/v1/reports/weekly/export?date={$date}")
        ->assertOk();

    expect($response->streamedContent())->toContain("'=danger");
});

it('prevents cross-user model access through policies', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $log = WorkLog::factory()->for($owner)->create();

    $this->actingAs($other)
        ->getJson("/api/v1/work-logs/{$log->id}")
        ->assertForbidden();

    $this->actingAs($other)
        ->patchJson("/api/v1/work-logs/{$log->id}", [
            'date' => now()->toDateString(),
            'project_name' => 'Hijack',
            'title' => 'Hijack',
            'category' => 'feature',
            'status' => 'done',
            'priority' => 'medium',
        ])
        ->assertForbidden();
});
