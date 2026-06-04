<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\GameRecord;
use App\Models\GameSession;
use App\Services\SudokuGenerator;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GameController extends Controller
{
    public function startSession(Request $request): JsonResponse
    {
        $data = $request->validate([
            'level' => ['required', 'in:easy,medium,hard,expert,daily'],
        ]);

        // Preserve daily sessions — only abandon non-daily active sessions
        GameSession::ownedBy($request->user())
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
        // Exclude daily sessions — they are managed via dailyStatus
        $session = GameSession::ownedBy($request->user())
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
            'user_state' => ['required', 'array'],
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

    private function recordPayload(GameRecord $record): array
    {
        return [
            'id' => $record->id,
            'level' => $record->level,
            'duration_seconds' => $record->duration_seconds,
            'completed_at' => $record->completed_at?->toDateTimeString(),
        ];
    }
}
