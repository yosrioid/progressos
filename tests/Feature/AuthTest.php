<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;

it('registers, authenticates, and logs out through the REST API', function () {
    $this->postJson('/api/register', [
        'name' => 'Ada Progress',
        'email' => 'ada@example.com',
        'timezone' => 'UTC',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ])->assertCreated()->assertJsonPath('user.email', 'ada@example.com');

    $this->assertAuthenticated();

    $this->postJson('/api/logout')->assertOk();
    $this->assertGuest();

    $this->postJson('/api/login', ['email' => 'ada@example.com', 'password' => 'password123'])
        ->assertOk()
        ->assertJsonPath('user.email', 'ada@example.com');
});

it('updates profile and password through the REST API', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->patchJson('/api/profile', [
        'name' => 'Updated User',
        'email' => 'updated@example.com',
        'timezone' => 'Asia/Jakarta',
        'theme' => 'dark',
    ])->assertOk()->assertJsonPath('user.theme', 'dark');

    $this->actingAs($user)->putJson('/api/profile/password', [
        'current_password' => 'password',
        'password' => 'new-password',
        'password_confirmation' => 'new-password',
    ])->assertOk();

    expect(Hash::check('new-password', $user->fresh()->password))->toBeTrue();
});
