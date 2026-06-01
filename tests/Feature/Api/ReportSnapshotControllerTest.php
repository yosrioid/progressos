<?php

use App\Models\User;
use App\Models\WorkLog;

it('stores and lists report snapshots', function () {
    $user = User::factory()->create();
    WorkLog::factory()->for($user)->create([
        'date' => '2026-06-01',
        'status' => 'done',
        'actual_duration' => 45,
    ]);

    $this->actingAs($user)
        ->postJson('/api/v1/reports/weekly/snapshots?date=2026-06-01')
        ->assertCreated()
        ->assertJsonPath('snapshot.period_type', 'weekly');

    $this->actingAs($user)
        ->getJson('/api/v1/reports/weekly/snapshots')
        ->assertOk()
        ->assertJsonPath('snapshots.0.period_type', 'weekly');
});
