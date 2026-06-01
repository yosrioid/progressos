<?php

use App\Models\User;
use App\Models\WorkLog;

it('deduplicates quick capture requests with an idempotency key', function () {
    $user = User::factory()->create();

    $payload = [
        'type' => 'work_log',
        'title' => 'Idempotent work log',
        'project_name' => 'ProgressOS',
        'duration_minutes' => 20,
    ];

    $this->actingAs($user)->withHeader('Idempotency-Key', 'same-request')
        ->postJson('/api/v1/quick-capture', $payload)
        ->assertCreated();

    $this->actingAs($user)->withHeader('Idempotency-Key', 'same-request')
        ->postJson('/api/v1/quick-capture', $payload)
        ->assertCreated();

    expect(WorkLog::query()->where('title', 'Idempotent work log')->count())->toBe(1);
});
