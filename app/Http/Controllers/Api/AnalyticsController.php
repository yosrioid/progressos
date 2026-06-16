<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Goal;
use App\Models\HabitLog;
use App\Models\LearningEntry;
use App\Models\Task;
use App\Models\WorkLog;
use App\Support\ApiResponse;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class AnalyticsController extends Controller
{
    public function __invoke(Request $request)
    {
        $user = $request->user();

        $cached = Cache::get("analytics:{$user->id}");
        if ($cached !== null) {
            return ApiResponse::ok($cached);
        }

        $now = CarbonImmutable::now($user->timezone ?? 'UTC');
        $from90 = $now->subDays(89)->toDateString();

        // Work log heatmap — 90 days
        $workHeatRaw = $user->workLogs()
            ->selectRaw('DATE(date) as day, sum(actual_duration) as minutes')
            ->whereDate('date', '>=', $from90)
            ->groupByRaw('DATE(date)')
            ->pluck('minutes', 'day');

        $workHeatmap = collect(range(0, 89))->map(fn ($i) => [
            'date' => $now->subDays(89 - $i)->toDateString(),
            'minutes' => (int) ($workHeatRaw[$now->subDays(89 - $i)->toDateString()] ?? 0),
        ]);

        // Task velocity — fetch once, bucket in PHP (8 queries → 1)
        $from8Weeks = $now->subWeeks(8)->startOfWeek();
        $completedTasks = $user->tasks()
            ->where('status', 'done')
            ->whereNotNull('completed_at')
            ->whereDate('completed_at', '>=', $from8Weeks)
            ->pluck('completed_at');

        $velocity = collect(range(7, 0))->map(function (int $weeksAgo) use ($now, $completedTasks) {
            $weekStart = $now->subWeeks($weeksAgo)->startOfWeek();
            $weekEnd = $weekStart->endOfWeek();

            return [
                'week' => $weekStart->format('M d'),
                'count' => $completedTasks->filter(fn ($d) => $d->between($weekStart, $weekEnd))->count(),
            ];
        });

        // Monthly work hours — 6 months
        $monthExpr = DB::getDriverName() === 'sqlite'
            ? "strftime('%Y-%m', date)"
            : "DATE_FORMAT(date, '%Y-%m')";

        $monthly = $user->workLogs()
            ->selectRaw("{$monthExpr} as month, sum(actual_duration) as minutes, count(*) as logs")
            ->whereDate('date', '>=', $now->subMonths(6)->startOfMonth())
            ->groupByRaw($monthExpr)
            ->orderBy('month')
            ->get();

        // Work by category — all time
        $byCategory = $user->workLogs()
            ->selectRaw('category, sum(actual_duration) as minutes, count(*) as logs')
            ->groupBy('category')
            ->orderByDesc('minutes')
            ->get();

        // Task status breakdown
        $taskStatus = $user->tasks()
            ->selectRaw('status, count(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status');

        // Learning vs work comparison — fetch once, bucket in PHP (8 queries → 2)
        $from4Weeks = $now->subWeeks(4)->startOfWeek();
        $learnEntries = $user->learningEntries()->whereDate('date', '>=', $from4Weeks)->get(['date', 'duration_minutes']);
        $workEntries = $user->workLogs()->whereDate('date', '>=', $from4Weeks)->get(['date', 'actual_duration']);

        $learnWeeks = collect(range(3, 0))->map(function (int $w) use ($now, $learnEntries, $workEntries) {
            $weekStart = $now->subWeeks($w)->startOfWeek();
            $weekEnd = $weekStart->endOfWeek();

            return [
                'week' => $weekStart->format('M d'),
                'learning' => $learnEntries->filter(fn (LearningEntry $e) => (string) $e->date >= $weekStart->toDateString() && (string) $e->date <= $weekEnd->toDateString())->sum('duration_minutes'),
                'work' => $workEntries->filter(fn (WorkLog $e) => (string) $e->date >= $weekStart->toDateString() && (string) $e->date <= $weekEnd->toDateString())->sum('actual_duration'),
            ];
        });

        // Habit completion rates — fetch once, bucket in PHP (4 queries → 1)
        $activeHabitCount = $user->habits()->where('active', true)->count();
        $habitLogEntries = $user->habitLogs()
            ->whereDate('date', '>=', $now->subWeeks(4)->startOfWeek()->toDateString())
            ->get(['habit_id', 'date']);

        $habitWeeks = collect(range(3, 0))->map(function (int $w) use ($now, $activeHabitCount, $habitLogEntries) {
            $week = $now->subWeeks($w)->startOfWeek();
            $start = $week->toDateString();
            $end = $week->endOfWeek()->toDateString();
            $logged = $habitLogEntries
                ->filter(fn (HabitLog $l) => (string) $l->date >= $start && (string) $l->date <= $end)
                ->pluck('habit_id')->unique()->count();

            return [
                'week' => $week->format('M d'),
                'rate' => $activeHabitCount > 0 ? round(($logged / $activeHabitCount) * 100) : 0,
                'logged' => $logged,
                'active' => $activeHabitCount,
            ];
        });

        // Active goals summary
        $activeGoals = $user->goals()->where('status', 'active')->with('keyResults')->orderByDesc('created_at')->get()
            ->map(fn (Goal $g) => ['id' => $g->id, 'title' => $g->title, 'color' => $g->color, 'progress' => round($g->progressPercent())]);

        // Totals
        $totalWorkLogs = $user->workLogs()->count();
        $totalWorkMins = $user->workLogs()->sum('actual_duration');
        $totalTasksDone = $user->tasks()->where('status', 'done')->count();
        $totalTasks = $user->tasks()->count();
        $totalLearningMins = $user->learningEntries()->sum('duration_minutes');
        $totalDailyProgress = $user->dailyProgressEntries()->count();

        // Productivity score: weighted composite
        $score = min(100, round(
            ($totalTasksDone / max(1, $totalTasks)) * 30
            + min(30, $totalDailyProgress / 5)
            + min(20, $totalLearningMins / 600)
            + min(20, $totalWorkMins / 3000)
        ));

        $payload = [
            'work_heatmap' => $workHeatmap,
            'task_velocity' => $velocity,
            'monthly_work' => $monthly,
            'by_category' => $byCategory,
            'task_status' => $taskStatus,
            'learn_vs_work' => $learnWeeks,
            'totals' => [
                'work_logs' => $totalWorkLogs,
                'work_minutes' => $totalWorkMins,
                'tasks_done' => $totalTasksDone,
                'tasks_total' => $totalTasks,
                'learning_minutes' => $totalLearningMins,
                'daily_progress' => $totalDailyProgress,
                'completion_rate' => $totalTasks > 0 ? round($totalTasksDone / $totalTasks * 100) : 0,
            ],
            'productivity_score' => $score,
            'habit_weeks' => $habitWeeks,
            'active_goals' => $activeGoals,
        ];

        Cache::put("analytics:{$user->id}", $payload, now()->addMinutes(5));

        return ApiResponse::ok($payload);
    }
}
