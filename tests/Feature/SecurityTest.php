<?php

use App\Models\Task;
use App\Models\User;
use App\Models\WorkLog;
use App\Rules\SafeHttpUrl;
use Illuminate\Support\Facades\Validator;

it('adds security headers to SPA responses', function () {
    $response = $this->get('/login');
    $response->assertHeader('X-Frame-Options', 'SAMEORIGIN');
    $response->assertHeader('X-Content-Type-Options', 'nosniff');
    $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
    // CSP must be present with at least one directive (default-src 'self')
    expect($response->headers->get('Content-Security-Policy'))
        ->toContain("default-src 'self'")
        ->toContain("frame-ancestors 'none'");
});

it('disables sensitive browser features via Permissions-Policy', function () {
    $response = $this->get('/login');

    $permissionsPolicy = $response->headers->get('Permissions-Policy');
    expect($permissionsPolicy)
        ->toContain('camera=()')
        ->toContain('microphone=()')
        ->toContain('geolocation=()');
});

it('prevents API responses from being cached by browsers or proxies', function () {
    $user = User::factory()->create();
    $response = $this->actingAs($user)->getJson('/api/v1/tasks');

    $cacheControl = $response->headers->get('Cache-Control');
    expect($cacheControl)
        ->toContain('no-store')
        ->toContain('no-cache')
        ->toContain('must-revalidate')
        ->toContain('private');
    expect($response->headers->get('Pragma'))->toBe('no-cache');
});

it('disallows embedding the app in a frame from another origin', function () {
    $response = $this->get('/login');

    expect($response->headers->get('Content-Security-Policy'))
        ->toContain("frame-ancestors 'none'");
    expect($response->headers->get('X-Frame-Options'))->toBe('SAMEORIGIN');
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
