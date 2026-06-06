<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;

class StandupController extends Controller
{
    public function __invoke(Request $request)
    {
        $user = $request->user();
        $tz = $user->timezone ?? 'UTC';
        $today = CarbonImmutable::today($tz);
        $yesterday = $today->subDay();

        $doneLogs = $user->workLogs()
            ->with('project')
            ->whereDate('date', $yesterday)
            ->where('status', 'done')
            ->get(['id', 'title', 'project_name', 'actual_duration', 'date']);

        $doneToday = $user->workLogs()
            ->whereDate('date', $today)
            ->where('status', 'done')
            ->get(['id', 'title', 'project_name', 'actual_duration']);

        $completedTasks = $user->tasks()
            ->where('status', 'done')
            ->whereBetween('completed_at', [$yesterday->startOfDay(), $today->endOfDay()])
            ->get(['id', 'title', 'priority']);

        $todayTasks = $user->tasks()
            ->whereIn('status', ['todo', 'in_progress'])
            ->orderByDesc('status')
            ->orderBy('priority')
            ->limit(8)
            ->get(['id', 'title', 'status', 'priority', 'project_id']);

        $blockers = $user->workLogs()
            ->where('status', 'blocked')
            ->latest('date')
            ->limit(5)
            ->get(['id', 'title', 'project_name']);

        $blockerTasks = $user->tasks()
            ->where('status', 'blocked')
            ->limit(5)
            ->get(['id', 'title']);

        // Build pre-formatted standup text
        $lines = [];
        $lines[] = '## Standup — '.$today->toFormattedDateString();
        $lines[] = '';
        $lines[] = '### ✅ Kemarin / Selesai';
        foreach ($doneLogs as $l) {
            $lines[] = "- {$l->title}".($l->project_name ? " [{$l->project_name}]" : '');
        }
        foreach ($doneToday as $l) {
            $lines[] = "- {$l->title}".($l->project_name ? " [{$l->project_name}]" : '');
        }
        foreach ($completedTasks as $t) {
            $lines[] = "- ✓ Task: {$t->title}";
        }
        if ($doneLogs->isEmpty() && $doneToday->isEmpty() && $completedTasks->isEmpty()) {
            $lines[] = '- (tidak ada yang selesai)';
        }
        $lines[] = '';
        $lines[] = '### 📋 Hari ini';
        foreach ($todayTasks as $t) {
            $prefix = $t->status === 'in_progress' ? '🔄' : '▫';
            $lines[] = "{$prefix} {$t->title}";
        }
        if ($todayTasks->isEmpty()) {
            $lines[] = '- (belum ada task yang direncanakan)';
        }
        $lines[] = '';
        $lines[] = '### 🚧 Blocker';
        $allBlockers = $blockers->merge($blockerTasks->map(fn ($t) => (object) ['title' => $t->title, 'project_name' => null]));
        foreach ($allBlockers as $b) {
            $lines[] = "- ⛔ {$b->title}";
        }
        if ($allBlockers->isEmpty()) {
            $lines[] = '- Tidak ada blocker';
        }

        return response()->json([
            'date' => $today->toDateString(),
            'done_logs' => $doneLogs->merge($doneToday)->values(),
            'completed_tasks' => $completedTasks->values(),
            'today_tasks' => $todayTasks->values(),
            'blockers' => $allBlockers->values(),
            'text' => implode("\n", $lines),
        ]);
    }
}
