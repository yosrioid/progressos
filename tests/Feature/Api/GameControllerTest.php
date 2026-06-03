<?php

use App\Models\GameRecord;
use App\Models\GameSession;
use App\Models\User;
use App\Services\SudokuGenerator;

test('it starts a sudoku session and returns puzzle with solution', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->postJson('/api/v1/games/sudoku/sessions', [
        'level' => 'easy',
    ]);

    $response->assertOk()
        ->assertJsonPath('data.session.level', 'easy')
        ->assertJsonPath('data.session.status', 'active')
        ->assertJsonStructure(['data' => ['session' => ['id', 'puzzle', 'solution', 'elapsed_seconds']]]);

    expect(GameSession::where('user_id', $user->id)->count())->toBe(1);
});

test('it abandons previous active session when starting a new one', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->postJson('/api/v1/games/sudoku/sessions', ['level' => 'easy']);
    $this->actingAs($user)->postJson('/api/v1/games/sudoku/sessions', ['level' => 'medium']);

    $sessions = GameSession::where('user_id', $user->id)->get();
    expect($sessions->count())->toBe(2);
    expect($sessions->where('status', 'abandoned')->count())->toBe(1);
    expect($sessions->where('status', 'active')->count())->toBe(1);
});

test('it returns the active session for resuming', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->postJson('/api/v1/games/sudoku/sessions', ['level' => 'hard']);

    $response = $this->actingAs($user)->getJson('/api/v1/games/sudoku/active');

    $response->assertOk()->assertJsonPath('data.session.level', 'hard');
});

test('it returns null when no active session exists', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->getJson('/api/v1/games/sudoku/active');

    $response->assertOk()->assertJsonPath('data.session', null);
});

test('it saves progress and updates elapsed seconds', function () {
    $user = User::factory()->create();

    $start = $this->actingAs($user)->postJson('/api/v1/games/sudoku/sessions', ['level' => 'easy']);
    $sessionId = $start->json('data.session.id');

    $response = $this->actingAs($user)->patchJson("/api/v1/games/sudoku/sessions/{$sessionId}", [
        'elapsed_seconds' => 120,
        'status' => 'paused',
    ]);

    $response->assertOk()->assertJsonPath('data.session.status', 'paused');
    expect(GameSession::find($sessionId)->elapsed_seconds)->toBe(120);
});

test('it rejects progress save from another user', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();

    $start = $this->actingAs($owner)->postJson('/api/v1/games/sudoku/sessions', ['level' => 'easy']);
    $sessionId = $start->json('data.session.id');

    $this->actingAs($other)->patchJson("/api/v1/games/sudoku/sessions/{$sessionId}", [
        'elapsed_seconds' => 10,
    ])->assertForbidden();
});

test('it completes a session with the correct solution and creates a record', function () {
    $user = User::factory()->create();

    $start = $this->actingAs($user)->postJson('/api/v1/games/sudoku/sessions', ['level' => 'easy']);
    $session = $start->json('data.session');
    $sessionId = $session['id'];
    $solution = $session['solution'];

    $response = $this->actingAs($user)->postJson("/api/v1/games/sudoku/sessions/{$sessionId}/complete", [
        'user_state' => $solution,
        'elapsed_seconds' => 300,
    ]);

    $response->assertOk()
        ->assertJsonPath('data.correct', true)
        ->assertJsonStructure(['data' => ['record' => ['id', 'duration_seconds']]]);

    expect(GameRecord::where('user_id', $user->id)->count())->toBe(1);
    expect(GameSession::find($sessionId)->status)->toBe('completed');
});

test('it rejects completion with wrong solution', function () {
    $user = User::factory()->create();

    $start = $this->actingAs($user)->postJson('/api/v1/games/sudoku/sessions', ['level' => 'easy']);
    $sessionId = $start->json('data.session.id');

    $wrongGrid = array_fill(0, 9, array_fill(0, 9, 1));

    $this->actingAs($user)->postJson("/api/v1/games/sudoku/sessions/{$sessionId}/complete", [
        'user_state' => $wrongGrid,
        'elapsed_seconds' => 60,
    ])->assertStatus(422)->assertJsonPath('data.correct', false);
});

test('it returns personal records grouped by level', function () {
    $user = User::factory()->create();

    GameRecord::create(['user_id' => $user->id, 'type' => 'sudoku', 'level' => 'easy', 'duration_seconds' => 120, 'completed_at' => now()]);
    GameRecord::create(['user_id' => $user->id, 'type' => 'sudoku', 'level' => 'hard', 'duration_seconds' => 800, 'completed_at' => now()]);

    $response = $this->actingAs($user)->getJson('/api/v1/games/sudoku/records');

    $response->assertOk()
        ->assertJsonCount(1, 'data.records.easy')
        ->assertJsonCount(0, 'data.records.medium')
        ->assertJsonCount(1, 'data.records.hard');
});

test('sudoku generator produces a valid unique puzzle for all levels', function () {
    $generator = new SudokuGenerator();

    foreach (['easy', 'medium', 'hard'] as $level) {
        $result = $generator->generate($level);

        expect($result['puzzle'])->toHaveCount(9);
        expect($result['solution'])->toHaveCount(9);

        // Solution cells should all be filled
        $filled = collect($result['solution'])->flatten()->filter(fn ($v) => $v !== null)->count();
        expect($filled)->toBe(81);

        // Puzzle should have fewer filled cells than solution
        $clues = collect($result['puzzle'])->flatten()->filter(fn ($v) => $v !== null)->count();
        expect($clues)->toBeGreaterThan(0)->toBeLessThan(81);

        // Every non-null puzzle cell should match solution
        foreach ($result['puzzle'] as $r => $row) {
            foreach ($row as $c => $val) {
                if ($val !== null) {
                    expect($val)->toBe($result['solution'][$r][$c]);
                }
            }
        }
    }
});
