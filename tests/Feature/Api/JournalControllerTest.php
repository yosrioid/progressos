<?php

use App\Models\Configuration;
use App\Models\Journal;
use App\Models\User;
use Illuminate\Support\Facades\Http;

it('rejects analyze when API key is missing', function () {
    $user = User::factory()->create();
    $journal = Journal::factory()->for($user)->create();

    // provider_config is missing entirely -> no API key -> 422
    $this->actingAs($user)->postJson("/api/v1/journals/{$journal->id}/analyze")
        ->assertStatus(422)
        ->assertJsonPath('error', 'no_api_key');
});

it('rejects analyze when user has already exceeded their quota', function () {
    $user = User::factory()->create();
    $journal = Journal::factory()->for($user)->create();

    // Store an encrypted provider_config so the API key resolves
    Configuration::setValue(null, 'ai', 'provider_config', [
        'groq_api_key' => 'gsk_test_key',
        'adacode_api_key' => '',
        'adacode_base_url' => '',
        'model' => 'claude-sonnet-4-6',
    ], encrypted: true);

    // Pre-fill today's usage to exceed the daily limit
    Configuration::setValue($user, 'groq', 'usage', [
        'date' => now()->toDateString(),
        'requests' => 50000,
        'tokens' => 1000000,
    ]);

    $this->actingAs($user)->postJson("/api/v1/journals/{$journal->id}/analyze")
        ->assertStatus(429)
        ->assertJsonPath('error', 'quota_exceeded');
});

it('analyzes a journal, persists the AI fields, and updates the persistent profile', function () {
    Http::fake([
        'api.groq.com/*' => Http::response([
            'choices' => [[
                'message' => ['content' => json_encode([
                    'mood' => 'lelah tapi lega',
                    'tema' => 'pekerjaan, keluarga',
                    'content' => 'Hari ini kamu menyelesaikan presentasi besar.',
                    'insight' => 'Ini ketiga kalinya kamu menyelesaikan presentasi besar dengan tenang.',
                    'saran' => 'Besok coba blok 2 jam tanpa notif.',
                    'profile' => 'Profil terbaru: pekerja keras yang peduli keluarga.',
                ])],
            ]],
            'usage' => ['total_tokens' => 250],
        ], 200),
    ]);

    $user = User::factory()->create();
    $journal = Journal::factory()->for($user)->create(['body' => 'Hari ini saya presentasi di kantor.']);

    Configuration::setValue(null, 'ai', 'provider_config', [
        'groq_api_key' => 'gsk_test_key',
        'adacode_api_key' => '',
        'adacode_base_url' => '',
        'model' => 'claude-sonnet-4-6',
    ], encrypted: true);

    $this->actingAs($user)->postJson("/api/v1/journals/{$journal->id}/analyze")
        ->assertOk()
        ->assertJsonPath('journal.mood', 'lelah tapi lega')
        ->assertJsonPath('journal.tema', 'pekerjaan, keluarga')
        ->assertJsonPath('journal.ai_content', 'Hari ini kamu menyelesaikan presentasi besar.');

    $journal->refresh();
    expect($journal->mood)->toBe('lelah tapi lega');
    expect($journal->ai_content)->toContain('presentasi');
    expect($journal->analyzed_at)->not->toBeNull();

    // Persistent AI profile should be written
    $profile = Configuration::getValue($user, 'journal', 'ai_profile');
    expect($profile['text'] ?? null)->toContain('Profil terbaru');
    expect($profile['entry_count'] ?? 0)->toBe(1);
});

it('returns 503 when the AI provider fails', function () {
    Http::fake([
        'api.groq.com/*' => Http::response([
            'error' => ['code' => 'rate_limit_exceeded', 'message' => 'too many'],
        ], 429),
    ]);

    $user = User::factory()->create();
    $journal = Journal::factory()->for($user)->create();

    Configuration::setValue(null, 'ai', 'provider_config', [
        'groq_api_key' => 'gsk_test_key',
        'adacode_api_key' => '',
        'adacode_base_url' => '',
        'model' => 'claude-sonnet-4-6',
    ], encrypted: true);

    $this->actingAs($user)->postJson("/api/v1/journals/{$journal->id}/analyze")
        ->assertStatus(503)
        ->assertJsonPath('error', 'ai_failed');
});

it('tracks token usage after a successful analysis', function () {
    Http::fake([
        'api.groq.com/*' => Http::response([
            'choices' => [[
                'message' => ['content' => json_encode([
                    'mood' => 'senang', 'tema' => 'hobi', 'content' => 'ok',
                    'insight' => 'ok', 'saran' => 'ok', 'profile' => '',
                ])],
            ]],
            'usage' => ['total_tokens' => 123],
        ], 200),
    ]);

    $user = User::factory()->create();
    $journal = Journal::factory()->for($user)->create();

    Configuration::setValue(null, 'ai', 'provider_config', [
        'groq_api_key' => 'gsk_test_key',
        'adacode_api_key' => '', 'adacode_base_url' => '', 'model' => '',
    ], encrypted: true);

    $this->actingAs($user)->postJson("/api/v1/journals/{$journal->id}/analyze")->assertOk();

    $stored = Configuration::getValue($user, 'groq', 'usage');
    expect($stored['tokens'] ?? 0)->toBe(123);
    expect($stored['requests'] ?? 0)->toBe(1);
});
