<?php

use App\Models\Configuration;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

beforeEach(function () {
    Cache::flush();
});

it('saves and retrieves quote config encrypted', function () {
    $user = User::factory()->create();

    // The actual route is PUT /api/quote/config (singular)
    $this->actingAs($user)->putJson('/api/v1/quote/config', [
        'enabled' => true,
        'themes' => ['motivation', 'stoic'],
        'provider' => 'groq',
    ])->assertOk()
      ->assertJsonPath('quote_config.enabled', true)
      ->assertJsonPath('quote_config.themes.0', 'motivation');
});

it('rejects daily quote when quota is exceeded', function () {
    $user = User::factory()->create();

    // Pre-fill groq usage beyond the hard-coded daily limit
    Configuration::setValue($user, 'groq', 'usage', [
        'date' => now()->toDateString(),
        'requests' => 50000,
        'tokens' => 1000000,
    ]);

    // Enabled config so the route isn't short-circuited earlier
    Configuration::setValue(null, 'quote', 'groq', [
        'enabled' => true, 'themes' => ['motivation'], 'provider' => 'groq',
    ]);

    $this->actingAs($user)->getJson('/api/v1/quote/daily')
        ->assertStatus(429)
        ->assertJsonPath('data.quota_exceeded', true);
});

it('generates a quote and tracks usage', function () {
    $user = User::factory()->create();

    Configuration::setValue(null, 'quote', 'groq', [
        'enabled' => true, 'themes' => ['wisdom'], 'provider' => 'groq',
    ]);
    Configuration::setValue(null, 'ai', 'provider_config', [
        'groq_api_key' => 'gsk_test_key',
        'adacode_api_key' => '', 'adacode_base_url' => '', 'model' => '',
    ], encrypted: true);

    \Illuminate\Support\Facades\Http::fake([
        'api.groq.com/*' => \Illuminate\Support\Facades\Http::response([
            'choices' => [[
                'message' => ['content' => '{"quote":"Be yourself.","author":"Anonymous"}'],
            ]],
            'usage' => ['total_tokens' => 30],
        ], 200),
    ]);

    $this->actingAs($user)->getJson('/api/v1/quote/daily')
        ->assertOk()
        ->assertJsonPath('quote.quote', 'Be yourself.');

    // Usage was tracked back to this user (groq bucket)
    $stored = Configuration::getValue($user, 'groq', 'usage');
    expect($stored['requests'] ?? 0)->toBeGreaterThanOrEqual(1);
    expect($stored['tokens'] ?? 0)->toBe(30);
});

it('returns usage aggregated across providers', function () {
    $user = User::factory()->create();

    $today = now()->toDateString();
    Configuration::setValue($user, 'groq', 'usage', [
        'date' => $today, 'requests' => 3, 'tokens' => 150,
    ]);
    Configuration::setValue($user, 'adacode', 'usage', [
        'date' => $today, 'requests' => 2, 'tokens' => 80,
    ]);

    $this->actingAs($user)->getJson('/api/v1/quote/usage')
        ->assertOk()
        ->assertJsonPath('usage.groq_requests', 3)
        ->assertJsonPath('usage.adacode_requests', 2)
        ->assertJsonPath('usage.total_requests', 5)
        ->assertJsonPath('usage.total_tokens', 230);
});