<?php

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

it('registers, authenticates, and logs out through the REST API', function () {
    $this->postJson('/api/register', [
        'name' => 'Ada Progress',
        'email' => 'ada@example.com',
        'timezone' => 'UTC',
        'password' => 'Password123',
        'password_confirmation' => 'Password123',
    ])->assertCreated()->assertJsonPath('user.email', 'ada@example.com');

    $this->assertAuthenticated();

    $this->postJson('/api/logout')->assertOk();
    $this->assertGuest();

    $this->postJson('/api/login', ['email' => 'ada@example.com', 'password' => 'Password123'])
        ->assertOk()
        ->assertJsonPath('user.email', 'ada@example.com');
});

it('updates profile and password through the REST API', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->patchJson('/api/profile', [
        'name' => 'Updated User',
        'email' => 'updated@example.com',
        'timezone' => 'Asia/Jakarta',
    ])->assertOk()->assertJsonPath('user.name', 'Updated User');

    $this->actingAs($user)->putJson('/api/profile/password', [
        'current_password' => 'password',
        'password' => 'NewPassword1',
        'password_confirmation' => 'NewPassword1',
    ])->assertOk();

    expect(Hash::check('NewPassword1', $user->fresh()->password))->toBeTrue();
});

it('uploads avatar through the REST API', function () {
    Storage::fake('public');
    $user = User::factory()->create();

    $this->actingAs($user)->post('/api/profile/avatar', [
        'avatar' => UploadedFile::fake()->image('avatar.jpg', 240, 240),
    ])->assertOk()->assertJsonStructure(['user' => ['avatar_url']]);

    expect($user->fresh()->avatar_path)->not->toBeNull();
    Storage::disk('public')->assertExists($user->fresh()->avatar_path);
});

it('issues and revokes bearer tokens for REST API access', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)
        ->postJson('/api/tokens', ['name' => 'Raycast'])
        ->assertCreated()
        ->assertJsonPath('token.name', 'Raycast');

    $plainTextToken = $response->json('plain_text_token');

    $this->withHeader('Authorization', "Bearer {$plainTextToken}")
        ->getJson('/api/v1/dashboard')
        ->assertOk()
        ->assertJsonStructure(['summary']);

    $this->actingAs($user)
        ->deleteJson('/api/tokens/'.$response->json('token.id'))
        ->assertNoContent();

    Auth::guard('web')->logout();
    Auth::forgetGuards();

    $this->withHeader('Authorization', "Bearer {$plainTextToken}")
        ->getJson('/api/v1/dashboard')
        ->assertUnauthorized();
});

it('enforces API token abilities for write endpoints', function () {
    $user = User::factory()->create();
    $plainTextToken = $user->createToken('Read only', ['read'])->plainTextToken;

    $this->withHeader('Authorization', "Bearer {$plainTextToken}")
        ->getJson('/api/v1/dashboard')
        ->assertOk();

    $this->withHeader('Authorization', "Bearer {$plainTextToken}")
        ->postJson('/api/v1/tasks', [
            'title' => 'Should not be created',
            'status' => 'todo',
            'priority' => 'medium',
        ])
        ->assertForbidden();
});
