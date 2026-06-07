<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\GameRecord;
use App\Models\GameSession;
use App\Services\Game2048Generator;
use App\Services\MemoryMatchGenerator;
use App\Services\MinesweeperGenerator;
use App\Services\SudokuGenerator;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class GameController extends Controller
{
    // ── Sudoku ────────────────────────────────────────────────────────────────

    public function startSession(Request $request): JsonResponse
    {
        $data = $request->validate([
            'level' => ['required', 'in:easy,medium,hard,expert,daily'],
        ]);

        GameSession::ownedBy($request->user())
            ->where('type', 'sudoku')
            ->where('level', '!=', 'daily')
            ->whereIn('status', ['active', 'paused'])
            ->update(['status' => 'abandoned']);

        $generator = new SudokuGenerator;
        $generated = $data['level'] === 'daily'
            ? $generator->generateForDate(now()->toDateString())
            : $generator->generate($data['level']);

        $session = GameSession::create([
            'user_id' => $request->user()->id,
            'type' => 'sudoku',
            'level' => $data['level'],
            'puzzle' => $generated['puzzle'],
            'solution' => $generated['solution'],
            'user_state' => null,
            'notes_state' => null,
            'elapsed_seconds' => 0,
            'status' => 'active',
        ]);

        return ApiResponse::ok(['session' => $this->sessionPayload($session)]);
    }

    public function activeSession(Request $request): JsonResponse
    {
        $session = GameSession::ownedBy($request->user())
            ->where('type', 'sudoku')
            ->where('level', '!=', 'daily')
            ->whereIn('status', ['active', 'paused'])
            ->latest()
            ->first();

        return ApiResponse::ok(['session' => $session ? $this->sessionPayload($session) : null]);
    }

    public function dailyStatus(Request $request): JsonResponse
    {
        $user = $request->user();

        $completedRecord = GameRecord::ownedBy($user)
            ->where('type', 'sudoku')
            ->where('level', 'daily')
            ->whereDate('completed_at', today())
            ->orderBy('duration_seconds')
            ->first();

        $activeSession = GameSession::ownedBy($user)
            ->where('type', 'sudoku')
            ->where('level', 'daily')
            ->whereIn('status', ['active', 'paused'])
            ->latest()
            ->first();

        return ApiResponse::ok([
            'completed_today' => $completedRecord !== null,
            'record' => $completedRecord ? $this->recordPayload($completedRecord) : null,
            'session' => $activeSession ? $this->sessionPayload($activeSession) : null,
        ]);
    }

    public function saveProgress(Request $request, GameSession $session): JsonResponse
    {
        if ($session->user_id !== $request->user()->id) {
            abort(403);
        }

        $data = $request->validate([
            'user_state' => ['nullable', 'array'],
            'notes_state' => ['nullable', 'array'],
            'elapsed_seconds' => ['required', 'integer', 'min:0'],
            'status' => ['sometimes', 'in:active,paused,abandoned'],
        ]);

        $session->update($data);

        return ApiResponse::ok(['session' => $this->sessionPayload($session)]);
    }

    public function completeSession(Request $request, GameSession $session): JsonResponse
    {
        if ($session->user_id !== $request->user()->id) {
            abort(403);
        }

        $data = $request->validate([
            'user_state' => ['required', 'array', 'size:9'],
            'user_state.*' => ['array', 'size:9'],
            'elapsed_seconds' => ['required', 'integer', 'min:1'],
        ]);

        $solution = $session->solution;
        $userState = $data['user_state'];
        $correct = true;

        for ($r = 0; $r < 9; $r++) {
            for ($c = 0; $c < 9; $c++) {
                if ((int) ($userState[$r][$c] ?? 0) !== (int) ($solution[$r][$c] ?? 0)) {
                    $correct = false;
                    break 2;
                }
            }
        }

        if (! $correct) {
            return ApiResponse::ok(['correct' => false], 'Solusi tidak benar.', 422);
        }

        $session->update([
            'user_state' => $data['user_state'],
            'elapsed_seconds' => $data['elapsed_seconds'],
            'status' => 'completed',
        ]);

        $record = GameRecord::create([
            'user_id' => $request->user()->id,
            'type' => 'sudoku',
            'level' => $session->level,
            'duration_seconds' => $data['elapsed_seconds'],
            'completed_at' => now(),
        ]);

        $rank = GameRecord::ownedBy($request->user())
            ->where('type', 'sudoku')
            ->where('level', $session->level)
            ->where('duration_seconds', '<=', $record->duration_seconds)
            ->count();

        return ApiResponse::ok([
            'correct' => true,
            'record' => $this->recordPayload($record),
            'rank' => $rank,
        ]);
    }

    public function records(Request $request): JsonResponse
    {
        $user = $request->user();
        $result = [];
        $totals = [];

        foreach (['easy', 'medium', 'hard', 'expert', 'daily'] as $level) {
            $result[$level] = GameRecord::ownedBy($user)
                ->where('type', 'sudoku')
                ->where('level', $level)
                ->orderBy('duration_seconds')
                ->limit(10)
                ->get()
                ->map(fn (GameRecord $r) => $this->recordPayload($r))
                ->values();

            $totals[$level] = GameRecord::ownedBy($user)
                ->where('type', 'sudoku')
                ->where('level', $level)
                ->count();
        }

        return ApiResponse::ok(['records' => $result, 'totals' => $totals]);
    }

    // ── Minesweeper ───────────────────────────────────────────────────────────

    public function startMinesweeperSession(Request $request): JsonResponse
    {
        $data = $request->validate([
            'level' => ['required', 'in:beginner,intermediate,expert,daily,custom'],
            'rows' => ['required_if:level,custom', 'integer', 'min:5', 'max:30'],
            'cols' => ['required_if:level,custom', 'integer', 'min:5', 'max:50'],
            'mine_count' => ['required_if:level,custom', 'integer', 'min:1'],
        ]);

        $presets = [
            'beginner' => ['rows' => 9,  'cols' => 9,  'mines' => 10],
            'intermediate' => ['rows' => 16, 'cols' => 16, 'mines' => 40],
            'expert' => ['rows' => 16, 'cols' => 30, 'mines' => 99],
            'daily' => ['rows' => 16, 'cols' => 16, 'mines' => 40],
        ];

        if ($data['level'] === 'custom') {
            $rows = (int) $data['rows'];
            $cols = (int) $data['cols'];
            $mines = max(1, min((int) $data['mine_count'], $rows * $cols - 9));
            $level = "custom_{$rows}x{$cols}_{$mines}";
        } else {
            $p = $presets[$data['level']];
            $rows = $p['rows'];
            $cols = $p['cols'];
            $mines = $p['mines'];
            $level = $data['level'];
        }

        GameSession::ownedBy($request->user())
            ->where('type', 'minesweeper')
            ->when($data['level'] !== 'daily', fn ($q) => $q->where('level', '!=', 'daily'))
            ->whereIn('status', ['active', 'paused'])
            ->update(['status' => 'abandoned']);

        $generator = new MinesweeperGenerator;
        $puzzle = $generator->generate($rows, $cols, $mines, $data['level'] === 'daily' ? now()->toDateString() : null);

        $session = GameSession::create([
            'user_id' => $request->user()->id,
            'type' => 'minesweeper',
            'level' => $level,
            'puzzle' => $puzzle,
            'solution' => null,
            'user_state' => null,
            'notes_state' => null,
            'elapsed_seconds' => 0,
            'status' => 'active',
        ]);

        return ApiResponse::ok(['session' => $this->minesweeperSessionPayload($session)]);
    }

    public function activeMinesweeperSession(Request $request): JsonResponse
    {
        $session = GameSession::ownedBy($request->user())
            ->where('type', 'minesweeper')
            ->where('level', '!=', 'daily')
            ->whereIn('status', ['active', 'paused'])
            ->latest()
            ->first();

        return ApiResponse::ok(['session' => $session ? $this->minesweeperSessionPayload($session) : null]);
    }

    public function minesweeperDailyStatus(Request $request): JsonResponse
    {
        $user = $request->user();

        $completedRecord = GameRecord::ownedBy($user)
            ->where('type', 'minesweeper')
            ->where('level', 'daily')
            ->whereDate('completed_at', today())
            ->orderBy('duration_seconds')
            ->first();

        $activeSession = GameSession::ownedBy($user)
            ->where('type', 'minesweeper')
            ->where('level', 'daily')
            ->whereIn('status', ['active', 'paused'])
            ->latest()
            ->first();

        return ApiResponse::ok([
            'completed_today' => $completedRecord !== null,
            'record' => $completedRecord ? $this->recordPayload($completedRecord) : null,
            'session' => $activeSession ? $this->minesweeperSessionPayload($activeSession) : null,
        ]);
    }

    public function completeMinesweeperSession(Request $request, GameSession $session): JsonResponse
    {
        if ($session->user_id !== $request->user()->id) {
            abort(403);
        }

        $data = $request->validate([
            'elapsed_seconds' => ['required', 'integer', 'min:0'],
            'outcome' => ['required', 'in:won,lost'],
            'user_state' => ['nullable', 'array'],
        ]);

        $session->update([
            'user_state' => $data['user_state'] ?? $session->user_state,
            'elapsed_seconds' => $data['elapsed_seconds'],
            'status' => $data['outcome'] === 'won' ? 'completed' : 'failed',
        ]);

        if ($data['outcome'] === 'lost') {
            return ApiResponse::ok(['outcome' => 'lost']);
        }

        $record = GameRecord::create([
            'user_id' => $request->user()->id,
            'type' => 'minesweeper',
            'level' => $session->level,
            'duration_seconds' => $data['elapsed_seconds'],
            'completed_at' => now(),
        ]);

        $rank = GameRecord::ownedBy($request->user())
            ->where('type', 'minesweeper')
            ->where('level', $session->level)
            ->where('duration_seconds', '<=', $record->duration_seconds)
            ->count();

        return ApiResponse::ok([
            'outcome' => 'won',
            'record' => $this->recordPayload($record),
            'rank' => $rank,
        ]);
    }

    public function minesweeperRecords(Request $request): JsonResponse
    {
        $user = $request->user();
        $result = [];
        $totals = [];

        $standardLevels = ['beginner', 'intermediate', 'expert', 'daily'];
        $customLevels = GameRecord::ownedBy($user)
            ->where('type', 'minesweeper')
            ->where('level', 'like', 'custom_%')
            ->distinct()
            ->pluck('level')
            ->toArray();

        foreach (array_merge($standardLevels, $customLevels) as $level) {
            $result[$level] = GameRecord::ownedBy($user)
                ->where('type', 'minesweeper')
                ->where('level', $level)
                ->orderBy('duration_seconds')
                ->limit(10)
                ->get()
                ->map(fn (GameRecord $r) => $this->recordPayload($r))
                ->values();

            $totals[$level] = GameRecord::ownedBy($user)
                ->where('type', 'minesweeper')
                ->where('level', $level)
                ->count();
        }

        return ApiResponse::ok(['records' => $result, 'totals' => $totals]);
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    private function sessionPayload(GameSession $session): array
    {
        return [
            'id' => $session->id,
            'level' => $session->level,
            'puzzle' => $session->puzzle,
            'solution' => $session->solution,
            'user_state' => $session->user_state,
            'notes_state' => $session->notes_state,
            'elapsed_seconds' => $session->elapsed_seconds,
            'status' => $session->status,
        ];
    }

    private function minesweeperSessionPayload(GameSession $session): array
    {
        return [
            'id' => $session->id,
            'level' => $session->level,
            'puzzle' => $session->puzzle,
            'user_state' => $session->user_state,
            'elapsed_seconds' => $session->elapsed_seconds,
            'status' => $session->status,
        ];
    }

    // ── 2048 ──────────────────────────────────────────────────────────────────────

    public function start2048Session(Request $request): JsonResponse
    {
        $data = $request->validate([
            'level' => ['required', 'in:classic,large,challenge,daily'],
        ]);

        GameSession::ownedBy($request->user())
            ->where('type', '2048')
            ->when($data['level'] !== 'daily', fn ($q) => $q->where('level', '!=', 'daily'))
            ->whereIn('status', ['active', 'paused'])
            ->update(['status' => 'abandoned']);

        $generator = new Game2048Generator;
        $generated = $data['level'] === 'daily'
            ? $generator->generateForDate(now()->toDateString())
            : $generator->generate($data['level']);

        $session = GameSession::create([
            'user_id' => $request->user()->id,
            'type' => '2048',
            'level' => $data['level'],
            'puzzle' => $generated,
            'solution' => null,
            'user_state' => ['board' => $generated['board'], 'score' => 0],
            'notes_state' => null,
            'elapsed_seconds' => 0,
            'status' => 'active',
        ]);

        return ApiResponse::ok(['session' => $this->payload2048($session)]);
    }

    public function active2048Session(Request $request): JsonResponse
    {
        $session = GameSession::ownedBy($request->user())
            ->where('type', '2048')
            ->where('level', '!=', 'daily')
            ->whereIn('status', ['active', 'paused'])
            ->latest()
            ->first();

        return ApiResponse::ok(['session' => $session ? $this->payload2048($session) : null]);
    }

    public function daily2048Status(Request $request): JsonResponse
    {
        $user = $request->user();

        $record = GameRecord::ownedBy($user)
            ->where('type', '2048')
            ->where('level', 'daily')
            ->whereDate('completed_at', today())
            ->get()
            ->sortByDesc(fn ($r) => $r->metadata['score'] ?? 0)
            ->first();

        $session = GameSession::ownedBy($user)
            ->where('type', '2048')
            ->where('level', 'daily')
            ->whereIn('status', ['active', 'paused'])
            ->latest()
            ->first();

        return ApiResponse::ok([
            'completed_today' => $record !== null,
            'record' => $record ? $this->recordPayload($record) : null,
            'session' => $session ? $this->payload2048($session) : null,
        ]);
    }

    public function complete2048Session(Request $request, GameSession $session): JsonResponse
    {
        if ($session->user_id !== $request->user()->id) {
            abort(403);
        }

        $data = $request->validate([
            'board' => ['required', 'array'],
            'score' => ['required', 'integer', 'min:0'],
            'max_tile' => ['required', 'integer', 'min:2'],
            'elapsed_seconds' => ['required', 'integer', 'min:0'],
        ]);

        $session->update([
            'user_state' => ['board' => $data['board'], 'score' => $data['score']],
            'elapsed_seconds' => $data['elapsed_seconds'],
            'status' => 'completed',
        ]);

        if ($data['score'] === 0) {
            return ApiResponse::ok(['record' => null, 'rank' => null]);
        }

        $record = GameRecord::create([
            'user_id' => $request->user()->id,
            'type' => '2048',
            'level' => $session->level,
            'duration_seconds' => $data['elapsed_seconds'],
            'metadata' => ['score' => $data['score'], 'max_tile' => $data['max_tile']],
            'completed_at' => now(),
        ]);

        $rank = GameRecord::ownedBy($request->user())
            ->where('type', '2048')
            ->where('level', $session->level)
            ->get()
            ->filter(fn ($r) => ($r->metadata['score'] ?? 0) > $data['score'])
            ->count() + 1;

        return ApiResponse::ok([
            'record' => $this->recordPayload($record),
            'rank' => $rank,
            'is_best' => $rank === 1,
        ]);
    }

    public function records2048(Request $request): JsonResponse
    {
        $user = $request->user();
        $result = [];
        $totals = [];

        foreach (['classic', 'large', 'challenge', 'daily'] as $level) {
            $records = GameRecord::ownedBy($user)
                ->where('type', '2048')
                ->where('level', $level)
                ->get()
                ->sortByDesc(fn ($r) => $r->metadata['score'] ?? 0)
                ->take(10)
                ->values();

            $result[$level] = $records->map(fn ($r) => $this->recordPayload($r))->values();
            $totals[$level] = GameRecord::ownedBy($user)->where('type', '2048')->where('level', $level)->count();
        }

        return ApiResponse::ok(['records' => $result, 'totals' => $totals]);
    }

    // ── Memory Match ──────────────────────────────────────────────────────────────

    public function startMemorySession(Request $request): JsonResponse
    {
        $presets = [
            'easy' => ['rows' => 4, 'cols' => 4, 'pairs' => 8],
            'medium' => ['rows' => 4, 'cols' => 6, 'pairs' => 12],
            'hard' => ['rows' => 6, 'cols' => 6, 'pairs' => 18],
            'daily' => ['rows' => 4, 'cols' => 6, 'pairs' => 12],
        ];

        $data = $request->validate([
            'level' => ['required', Rule::in(array_keys($presets))],
        ]);

        GameSession::ownedBy($request->user())
            ->where('type', 'memory')
            ->when($data['level'] !== 'daily', fn ($q) => $q->where('level', '!=', 'daily'))
            ->whereIn('status', ['active', 'paused'])
            ->update(['status' => 'abandoned']);

        $preset = $presets[$data['level']];
        $generator = new MemoryMatchGenerator;
        $puzzle = $generator->generate(
            $preset['rows'],
            $preset['cols'],
            $preset['pairs'],
            $data['level'] === 'daily' ? now()->toDateString() : null
        );

        $session = GameSession::create([
            'user_id' => $request->user()->id,
            'type' => 'memory',
            'level' => $data['level'],
            'puzzle' => $puzzle,
            'solution' => null,
            'user_state' => ['matched' => [], 'moves' => 0, 'mismatches' => 0],
            'notes_state' => null,
            'elapsed_seconds' => 0,
            'status' => 'active',
        ]);

        return ApiResponse::ok(['session' => $this->payloadMemory($session)]);
    }

    public function activeMemorySession(Request $request): JsonResponse
    {
        $session = GameSession::ownedBy($request->user())
            ->where('type', 'memory')
            ->where('level', '!=', 'daily')
            ->whereIn('status', ['active', 'paused'])
            ->latest()
            ->first();

        return ApiResponse::ok(['session' => $session ? $this->payloadMemory($session) : null]);
    }

    public function memoryDailyStatus(Request $request): JsonResponse
    {
        $user = $request->user();

        $record = GameRecord::ownedBy($user)
            ->where('type', 'memory')
            ->where('level', 'daily')
            ->whereDate('completed_at', today())
            ->orderBy('duration_seconds')
            ->first();

        $session = GameSession::ownedBy($user)
            ->where('type', 'memory')
            ->where('level', 'daily')
            ->whereIn('status', ['active', 'paused'])
            ->latest()
            ->first();

        return ApiResponse::ok([
            'completed_today' => $record !== null,
            'record' => $record ? $this->recordPayload($record) : null,
            'session' => $session ? $this->payloadMemory($session) : null,
        ]);
    }

    public function completeMemorySession(Request $request, GameSession $session): JsonResponse
    {
        if ($session->user_id !== $request->user()->id) {
            abort(403);
        }

        $data = $request->validate([
            'elapsed_seconds' => ['required', 'integer', 'min:1'],
            'moves' => ['required', 'integer', 'min:1'],
            'mismatches' => ['required', 'integer', 'min:0'],
        ]);

        $session->update([
            'elapsed_seconds' => $data['elapsed_seconds'],
            'status' => 'completed',
        ]);

        $perfect = $data['mismatches'] === 0;

        $record = GameRecord::create([
            'user_id' => $request->user()->id,
            'type' => 'memory',
            'level' => $session->level,
            'duration_seconds' => $data['elapsed_seconds'],
            'metadata' => ['moves' => $data['moves'], 'mismatches' => $data['mismatches'], 'perfect' => $perfect],
            'completed_at' => now(),
        ]);

        $rank = GameRecord::ownedBy($request->user())
            ->where('type', 'memory')
            ->where('level', $session->level)
            ->where('duration_seconds', '<', $data['elapsed_seconds'])
            ->count() + 1;

        return ApiResponse::ok([
            'record' => $this->recordPayload($record),
            'rank' => $rank,
            'perfect' => $perfect,
            'is_best' => $rank === 1,
        ]);
    }

    public function memoryRecords(Request $request): JsonResponse
    {
        $user = $request->user();
        $result = [];
        $totals = [];

        foreach (['easy', 'medium', 'hard', 'daily'] as $level) {
            $result[$level] = GameRecord::ownedBy($user)
                ->where('type', 'memory')
                ->where('level', $level)
                ->orderBy('duration_seconds')
                ->limit(10)
                ->get()
                ->map(fn ($r) => $this->recordPayload($r))
                ->values();

            $totals[$level] = GameRecord::ownedBy($user)->where('type', 'memory')->where('level', $level)->count();
        }

        return ApiResponse::ok(['records' => $result, 'totals' => $totals]);
    }

    // ── Melody Memory ─────────────────────────────────────────────────────────────

    public function startMelodySession(Request $request): JsonResponse
    {
        $presets = [
            'easy' => ['notes' => ['C', 'D', 'E', 'G', 'A']],
            'medium' => ['notes' => ['C', 'D', 'E', 'F', 'G', 'A', 'B']],
            'hard' => ['notes' => ['C', 'D', 'E', 'F', 'G', 'A', 'B']],
        ];

        $data = $request->validate([
            'level' => ['required', Rule::in(array_keys($presets))],
        ]);

        GameSession::ownedBy($request->user())
            ->where('type', 'melody')
            ->whereIn('status', ['active', 'paused'])
            ->update(['status' => 'abandoned']);

        $session = GameSession::create([
            'user_id' => $request->user()->id,
            'type' => 'melody',
            'level' => $data['level'],
            'puzzle' => ['level' => $data['level'], 'note_pool' => $presets[$data['level']]['notes']],
            'solution' => null,
            'user_state' => null,
            'notes_state' => null,
            'elapsed_seconds' => 0,
            'status' => 'active',
        ]);

        return ApiResponse::ok(['session' => $this->payloadMelody($session)]);
    }

    public function completeMelodySession(Request $request, GameSession $session): JsonResponse
    {
        if ($session->user_id !== $request->user()->id) {
            abort(403);
        }

        $data = $request->validate([
            'elapsed_seconds' => ['required', 'integer', 'min:1'],
            'streak' => ['required', 'integer', 'min:1'],
        ]);

        $session->update([
            'elapsed_seconds' => $data['elapsed_seconds'],
            'status' => 'completed',
        ]);

        $record = GameRecord::create([
            'user_id' => $request->user()->id,
            'type' => 'melody',
            'level' => $session->level,
            'duration_seconds' => $data['elapsed_seconds'],
            'metadata' => ['streak' => $data['streak']],
            'completed_at' => now(),
        ]);

        $rank = GameRecord::ownedBy($request->user())
            ->where('type', 'melody')
            ->where('level', $session->level)
            ->get()
            ->filter(fn ($r) => ($r->metadata['streak'] ?? 0) > $data['streak'])
            ->count() + 1;

        return ApiResponse::ok([
            'record' => $this->recordPayload($record),
            'rank' => $rank,
            'is_best' => $rank === 1,
        ]);
    }

    public function melodyRecords(Request $request): JsonResponse
    {
        $user = $request->user();
        $result = [];
        $totals = [];

        foreach (['easy', 'medium', 'hard'] as $level) {
            $records = GameRecord::ownedBy($user)
                ->where('type', 'melody')
                ->where('level', $level)
                ->get()
                ->sortByDesc(fn ($r) => $r->metadata['streak'] ?? 0)
                ->take(10)
                ->values();

            $result[$level] = $records->map(fn ($r) => $this->recordPayload($r))->values();
            $totals[$level] = GameRecord::ownedBy($user)->where('type', 'melody')->where('level', $level)->count();
        }

        return ApiResponse::ok(['records' => $result, 'totals' => $totals]);
    }

    // ── Pitch Trainer ──────────────────────────────────────────────────────────────

    public function startPitchSession(Request $request): JsonResponse
    {
        $presets = [
            'natural' => ['notes' => ['C', 'D', 'E', 'F', 'G', 'A', 'B'], 'time_limit' => 60],
            'chromatic' => ['notes' => ['C', 'C#', 'D', 'D#', 'E', 'F', 'F#', 'G', 'G#', 'A', 'A#', 'B'], 'time_limit' => 60],
        ];

        $data = $request->validate([
            'level' => ['required', Rule::in(array_keys($presets))],
        ]);

        GameSession::ownedBy($request->user())
            ->where('type', 'pitch')
            ->whereIn('status', ['active', 'paused'])
            ->update(['status' => 'abandoned']);

        $preset = $presets[$data['level']];

        $session = GameSession::create([
            'user_id' => $request->user()->id,
            'type' => 'pitch',
            'level' => $data['level'],
            'puzzle' => ['level' => $data['level'], 'note_pool' => $preset['notes'], 'time_limit' => $preset['time_limit']],
            'solution' => null,
            'user_state' => null,
            'notes_state' => null,
            'elapsed_seconds' => 0,
            'status' => 'active',
        ]);

        return ApiResponse::ok(['session' => $this->payloadPitch($session)]);
    }

    public function completePitchSession(Request $request, GameSession $session): JsonResponse
    {
        if ($session->user_id !== $request->user()->id) {
            abort(403);
        }

        $data = $request->validate([
            'elapsed_seconds' => ['required', 'integer', 'min:1'],
            'score' => ['required', 'integer', 'min:0'],
            'correct' => ['required', 'integer', 'min:0'],
            'total' => ['required', 'integer', 'min:1'],
        ]);

        $session->update([
            'elapsed_seconds' => $data['elapsed_seconds'],
            'status' => 'completed',
        ]);

        if ($data['score'] === 0) {
            return ApiResponse::ok(['record' => null, 'rank' => null]);
        }

        $accuracy = $data['total'] > 0 ? round(($data['correct'] / $data['total']) * 100) : 0;

        $record = GameRecord::create([
            'user_id' => $request->user()->id,
            'type' => 'pitch',
            'level' => $session->level,
            'duration_seconds' => $data['elapsed_seconds'],
            'metadata' => [
                'score' => $data['score'],
                'correct' => $data['correct'],
                'total' => $data['total'],
                'accuracy' => $accuracy,
            ],
            'completed_at' => now(),
        ]);

        $rank = GameRecord::ownedBy($request->user())
            ->where('type', 'pitch')
            ->where('level', $session->level)
            ->get()
            ->filter(fn ($r) => ($r->metadata['score'] ?? 0) > $data['score'])
            ->count() + 1;

        return ApiResponse::ok([
            'record' => $this->recordPayload($record),
            'rank' => $rank,
            'is_best' => $rank === 1,
        ]);
    }

    public function pitchRecords(Request $request): JsonResponse
    {
        $user = $request->user();
        $result = [];
        $totals = [];

        foreach (['natural', 'chromatic'] as $level) {
            $records = GameRecord::ownedBy($user)
                ->where('type', 'pitch')
                ->where('level', $level)
                ->get()
                ->sortByDesc(fn ($r) => $r->metadata['score'] ?? 0)
                ->take(10)
                ->values();

            $result[$level] = $records->map(fn ($r) => $this->recordPayload($r))->values();
            $totals[$level] = GameRecord::ownedBy($user)->where('type', 'pitch')->where('level', $level)->count();
        }

        return ApiResponse::ok(['records' => $result, 'totals' => $totals]);
    }

    // ── Private helpers ───────────────────────────────────────────────────────────

    private function payload2048(GameSession $session): array
    {
        return [
            'id' => $session->id,
            'level' => $session->level,
            'puzzle' => $session->puzzle,
            'user_state' => $session->user_state,
            'elapsed_seconds' => $session->elapsed_seconds,
            'status' => $session->status,
        ];
    }

    private function payloadMemory(GameSession $session): array
    {
        return [
            'id' => $session->id,
            'level' => $session->level,
            'puzzle' => $session->puzzle,
            'user_state' => $session->user_state,
            'elapsed_seconds' => $session->elapsed_seconds,
            'status' => $session->status,
        ];
    }

    private function payloadMelody(GameSession $session): array
    {
        return [
            'id' => $session->id,
            'level' => $session->level,
            'puzzle' => $session->puzzle,
            'elapsed_seconds' => $session->elapsed_seconds,
            'status' => $session->status,
        ];
    }

    private function payloadPitch(GameSession $session): array
    {
        return [
            'id' => $session->id,
            'level' => $session->level,
            'puzzle' => $session->puzzle,
            'elapsed_seconds' => $session->elapsed_seconds,
            'status' => $session->status,
        ];
    }

    private function recordPayload(GameRecord $record): array
    {
        return [
            'id' => $record->id,
            'level' => $record->level,
            'duration_seconds' => $record->duration_seconds,
            'metadata' => $record->metadata,
            'completed_at' => $record->completed_at?->toDateTimeString(),
        ];
    }
}
