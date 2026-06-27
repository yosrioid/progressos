<?php

use App\Models\Project;
use App\Models\User;

it('creates projects for the authenticated user', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->postJson('/api/v1/projects', [
            'name' => 'Website Redesign',
            'color' => 'sky',
        ])
        ->assertCreated()
        ->assertJsonPath('project.name', 'Website Redesign')
        ->assertJsonPath('project.color', 'sky');

    $this->assertDatabaseHas('projects', [
        'user_id' => $user->id,
        'name' => 'Website Redesign',
        'color' => 'sky',
    ]);
});

it('prevents duplicate project names for the same user', function () {
    $user = User::factory()->create();
    Project::create([
        'user_id' => $user->id,
        'name' => 'Website Redesign',
        'color' => 'teal',
    ]);

    $this->actingAs($user)
        ->postJson('/api/v1/projects', [
            'name' => 'Website Redesign',
            'color' => 'sky',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('name');
});
