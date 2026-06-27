<?php

use App\Models\User;

it('creates multiple work logs across projects in one request', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->postJson('/api/v1/work-logs/bulk', [
            'logs' => [
                [
                    'date' => '2026-06-27',
                    'project_name' => 'Client Portal',
                    'title' => 'Fix attendance report filter',
                    'category' => 'bug',
                    'status' => 'done',
                    'priority' => 'medium',
                    'actual_duration' => 90,
                ],
                [
                    'date' => '2026-06-27',
                    'project_name' => 'ProgressOS',
                    'title' => 'Add bulk work log capture',
                    'category' => 'feature',
                    'status' => 'done',
                    'priority' => 'high',
                    'actual_duration' => 120,
                ],
            ],
        ])
        ->assertCreated()
        ->assertJsonPath('created_count', 2)
        ->assertJsonCount(2, 'logs');

    $this->assertDatabaseHas('work_logs', [
        'user_id' => $user->id,
        'date' => '2026-06-27 00:00:00',
        'project_name' => 'Client Portal',
        'title' => 'Fix attendance report filter',
        'actual_duration' => 90,
    ]);

    $this->assertDatabaseHas('work_logs', [
        'user_id' => $user->id,
        'date' => '2026-06-27 00:00:00',
        'project_name' => 'ProgressOS',
        'title' => 'Add bulk work log capture',
        'actual_duration' => 120,
    ]);

    $this->assertDatabaseHas('projects', [
        'user_id' => $user->id,
        'name' => 'Client Portal',
    ]);

    $this->assertDatabaseHas('projects', [
        'user_id' => $user->id,
        'name' => 'ProgressOS',
    ]);
});

it('validates every bulk work log row', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->postJson('/api/v1/work-logs/bulk', [
            'logs' => [
                [
                    'date' => '2026-06-27',
                    'project_name' => '',
                    'title' => '',
                    'category' => 'invalid',
                    'status' => 'done',
                    'priority' => 'medium',
                ],
            ],
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors([
            'logs.0.project_name',
            'logs.0.title',
            'logs.0.category',
        ]);
});
