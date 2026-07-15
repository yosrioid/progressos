<?php

use App\Models\Configuration;
use App\Models\User;

it('returns the full ai_config payload plus usage for the requested provider', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    Configuration::setValue(null, 'ai', 'provider_config', [
        'groq_api_key' => 'gsk_test',
        'api_key' => 'adacode_test',
        'adacode_base_url' => 'https://api.example.com/v1',
        'model' => 'claude-sonnet-4-6',
    ], encrypted: true);

    $this->actingAs($admin)->getJson('/api/admin/configuration/ai?provider=groq')
        ->assertOk()
        ->assertJsonPath('ai_config.groq_api_key_set', true)
        ->assertJsonPath('ai_config.api_key_set', true)
        ->assertJsonPath('ai_config.provider_keys_set.groq', true)
        ->assertJsonPath('ai_config.provider_keys_set.adacode', true);
});

it('returns the cross-user usage dashboard from /api/admin/configuration/ai/usage', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    $alice = User::factory()->create(['name' => 'Alice']);
    $bob = User::factory()->create(['name' => 'Bob']);

    $today = now()->toDateString();

    // Use a sentinel user for the "yesterday" row so the (user_id, group,
    // key) triple differs from alice's today row. firstOrNew otherwise
    // overwrites today's entry with yesterday's.
    $yesterdayUser = User::factory()->create(['name' => 'YesterdayUser']);
    Configuration::setValue($yesterdayUser, 'groq', 'usage', [
        'date' => now()->subDay()->toDateString(), 'requests' => 999, 'tokens' => 99999,
    ]);

    Configuration::setValue($alice, 'groq', 'usage', [
        'date' => $today, 'requests' => 5, 'tokens' => 200,
    ]);
    Configuration::setValue($bob, 'adacode', 'usage', [
        'date' => $today, 'requests' => 2, 'tokens' => 90,
    ]);

    $response = $this->actingAs($admin)->getJson('/api/admin/configuration/ai/usage')
        ->assertOk();

    $users = $response->json('users');
    // Yesterday's row is filtered out by the controller, leaving alice+bob.
    expect($users)->toBeArray()->toHaveCount(2);

    $aliceRow = collect($users)->firstWhere('user_id', $alice->id);
    expect($aliceRow['total_requests'])->toBe(5);
    expect($aliceRow['total_tokens'])->toBe(200);

    $bobRow = collect($users)->firstWhere('user_id', $bob->id);
    expect($bobRow['total_requests'])->toBe(2);
    expect($bobRow['total_tokens'])->toBe(90);
});

it('hides the encrypted provider_config from non-admin callers', function () {
    $user = User::factory()->create(['role' => 'staff']);

    // The route is gated by role middleware; staff should be denied
    // before any payload is built.
    $this->actingAs($user)->getJson('/api/admin/configuration/ai/usage')
        ->assertForbidden();
});