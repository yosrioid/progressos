<?php

namespace App\Services;

use App\Models\User;
use Carbon\CarbonImmutable;

class DashboardData
{
    public function for(User $user, MilestoneProgressSync $milestones): array
    {
        $milestones->syncFor($user);

        $today = CarbonImmutable::now($user->timezone)->toDateString();
        $weekStart = CarbonImmutable::now($user->timezone)->startOfWeek();
        $monthStart = CarbonImmutable::now($user->timezone)->startOfMonth();

        $work = $user->workLogs();
        $learning = $user->learningEntries();
        $tasks = $user->tasks();

        return [
            'today' => [
                'date' => $today,
                'progress' => $user->dailyProgressEntries()->with('tags')->whereDate('date', $today)->latest()->get(),
                'tasks' => $user->tasks()->with(['project', 'milestone'])->whereIn('status', ['todo', 'in_progress', 'blocked'])->where(fn ($q) => $q->whereNull('due_date')->orWhereDate('due_date', '<=', $today))->orderBy('due_date')->take(8)->get(),
                'work_logs' => $user->workLogs()->whereDate('date', $today)->latest()->take(5)->get(),
                'learning' => $user->learningEntries()->whereDate('date', $today)->latest()->take(5)->get(),
            ],
            'summary' => [
                'today_progress' => $user->dailyProgressEntries()->whereDate('date', $today)->count(),
                'open_tasks' => (clone $tasks)->whereIn('status', ['todo', 'in_progress'])->count(),
                'completed_tasks' => (clone $tasks)->where('status', 'done')->count(),
                'blockers' => (clone $tasks)->where('status', 'blocked')->count() + (clone $work)->where('status', 'blocked')->count(),
                'work_logs_this_week' => (clone $work)->whereDate('date', '>=', $weekStart)->count(),
                'learning_sessions_this_week' => (clone $learning)->whereDate('date', '>=', $weekStart)->count(),
                'learning_minutes_this_week' => (clone $learning)->whereDate('date', '>=', $weekStart)->sum('duration_minutes'),
            ],
            'weekly_activity' => $this->activity($user, $weekStart, 7),
            'monthly_activity' => $this->activity($user, $monthStart, (int) $monthStart->daysInMonth),
            'latest_progress' => $user->dailyProgressEntries()->with('tags')->latest('date')->take(5)->get(),
            'latest_work_logs' => $user->workLogs()->with('tags')->latest('date')->take(5)->get(),
            'projects' => $user->projects()->withCount(['tasks as open_tasks_count' => fn ($q) => $q->whereIn('status', ['todo', 'in_progress', 'blocked'])])->where('archived', false)->orderBy('name')->take(8)->get(),
            'milestones' => $user->milestones()->whereIn('status', ['active', 'paused'])->orderBy('end_date')->take(6)->get()
                ->map(fn ($m) => $m->toArray() + ['progress_percent' => $m->progressPercent(), 'overdue' => $m->end_date?->isPast() && $m->status !== 'completed']),
            'streaks' => [
                'daily_progress' => $this->streak($user->dailyProgressEntries()->pluck('date')->map->toDateString()->all()),
                'learning' => $this->streak($user->learningEntries()->pluck('date')->map->toDateString()->all()),
            ],
        ];
    }

    private function activity(User $user, CarbonImmutable $start, int $days): array
    {
        return collect(range(0, $days - 1))->map(function ($offset) use ($user, $start) {
            $date = $start->addDays($offset)->toDateString();

            return [
                'date' => $date,
                'work' => $user->workLogs()->whereDate('date', $date)->count(),
                'learning' => $user->learningEntries()->whereDate('date', $date)->count(),
                'progress' => $user->dailyProgressEntries()->whereDate('date', $date)->count(),
            ];
        })->all();
    }

    private function streak(array $dates): int
    {
        $dateSet = array_flip(array_unique($dates));
        $cursor = CarbonImmutable::today();
        $streak = 0;

        while (isset($dateSet[$cursor->toDateString()])) {
            $streak++;
            $cursor = $cursor->subDay();
        }

        return $streak;
    }
}
