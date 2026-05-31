<?php

use App\Models\User;

it('registers, logs in, and logs out users', function () {
    $this->post('/register', [
        'name' => 'Ada Progress',
        'email' => 'ada@example.com',
        'timezone' => 'UTC',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ])->assertRedirect('/dashboard');

    $this->assertAuthenticated();

    $this->post('/logout')->assertRedirect('/login');
    $this->assertGuest();

    $this->post('/login', ['email' => 'ada@example.com', 'password' => 'password123'])
        ->assertRedirect('/dashboard');
});

it('updates profile and password', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->patch('/profile', [
        'name' => 'Updated User',
        'email' => 'updated@example.com',
        'timezone' => 'Asia/Jakarta',
        'theme' => 'dark',
    ])->assertSessionHasNoErrors()->assertRedirect();

    $this->actingAs($user)->put('/profile/password', [
        'current_password' => 'password',
        'password' => 'new-password',
        'password_confirmation' => 'new-password',
    ])->assertSessionHasNoErrors()->assertRedirect();
});
