<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Goal;
use App\Models\Task;
use App\Support\ApiResponse;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AnalyticsController extends Controller
{
    public function __invoke(Request $request)
    {
        $user = $request->user();
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

        // Task velocity — completed tasks per week for last 8 weeks
        $velocity = collect(range(7, 0))->map(function ($weeksAgo) use ($now) {
            $weekStart = $now->subWeeks($weeksAgo)->startOfWeek();
            $weekEnd = $weekStart->endOfWeek();

            return [
                'week' => $weekStart->format('M d'),
                'count' => Task::where('user_id', request()->user()->id)
                    ->where('status', 'done')
                    ->whereBetween('completed_at', [$weekStart, $weekEnd])
                    ->count(),
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

        // Learning vs work comparison — last 4 weeks each
        $learnWeeks = collect(range(3, 0))->map(function ($w) use ($now) {
            $start = $now->subWeeks($w)->startOfWeek();
            $end = $start->endOfWeek();

            return [
                'week' => $start->format('M d'),
                'learning' => request()->user()->learningEntries()->whereBetween('date', [$start, $end])->sum('duration_minutes'),
                'work' => request()->user()->workLogs()->whereBetween('date', [$start, $end])->sum('actual_duration'),
            ];
        });

        // Habit completion rates — last 4 weeks
        $activeHabitCount = $user->habits()->where('active', true)->count();
        $habitWeeks = collect(range(3, 0))->map(function (int $w) use ($user, $now, $activeHabitCount) {
            $week = $now->subWeeks($w)->startOfWeek();
            $start = $week->toDateString();
            $end = $week->endOfWeek()->toDateString();
            $logged = $user->habitLogs()->whereBetween('date', [$start, $end])->distinct('habit_id')->count('habit_id');

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

        return ApiResponse::ok([
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
        ]);
    }
}
