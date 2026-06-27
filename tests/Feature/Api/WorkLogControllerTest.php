<?php

use App\Models\User;

it('creates one work log for each selected project', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->postJson('/api/v1/work-logs', [
            'date' => '2026-06-27',
            'project_names' => ['Client Portal', 'ProgressOS'],
            'title' => 'Daily report cleanup',
            'category' => 'feature',
            'status' => 'done',
            'priority' => 'medium',
            'actual_duration' => 90,
        ])
        ->assertCreated()
        ->assertJsonPath('created_count', 2)
        ->assertJsonCount(2, 'logs');

    $this->assertDatabaseHas('work_logs', [
        'user_id' => $user->id,
        'project_name' => 'Client Portal',
        'title' => 'Daily report cleanup',
        'actual_duration' => 90,
    ]);

    $this->assertDatabaseHas('work_logs', [
        'user_id' => $user->id,
        'project_name' => 'ProgressOS',
        'title' => 'Daily report cleanup',
        'actual_duration' => 90,
    ]);
});
