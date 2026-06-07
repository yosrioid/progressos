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
            'recent_activity' => $user->auditLogs()->latest()->take(8)->get()->map(fn ($log) => [
                'id' => $log->id,
                'event' => $log->event,
                'label' => str($log->event)->replace('.', ' ')->headline()->toString(),
                'record_type' => class_basename((string) $log->auditable_type),
                'record_id' => $log->auditable_id,
                'created_at' => $log->created_at,
            ]),
            'streaks' => [
                'daily_progress' => $this->streak(
                    $user->dailyProgressEntries()->whereDate('date', '>=', now()->subDays(400))->pluck('date')->map->toDateString()->all()
                ),
                'learning' => $this->streak(
                    $user->learningEntries()->whereDate('date', '>=', now()->subDays(400))->pluck('date')->map->toDateString()->all()
                ),
            ],
            'focus' => [
                'overdue_tasks' => $user->tasks()
                    ->whereIn('status', ['todo', 'in_progress', 'blocked'])
                    ->whereNotNull('due_date')
                    ->whereDate('due_date', '<', $today)
                    ->orderBy('due_date')
                    ->take(5)
                    ->get(['id', 'title', 'status', 'priority', 'due_date']),
                'next_actions' => $user->learningEntries()
                    ->whereNotNull('next_action')
                    ->where('next_action', '!=', '')
                    ->orderByDesc('date')
                    ->take(5)
                    ->get(['id', 'topic', 'category', 'next_action', 'date']),
                'behind_milestones' => $user->milestones()
                    ->where('status', 'active')
                    ->whereNotNull('end_date')
                    ->whereDate('end_date', '>', $today)
                    ->get()
                    ->map(fn ($m) => $m->toArray() + ['progress_percent' => $m->progressPercent()])
                    ->filter(function ($m) {
                        if (! $m['start_date'] || $m['current_value'] <= 0) {
                            return false;
                        }
                        $elapsed = max(1, (int) now()->diffInDays($m['start_date']));
                        $rate = $m['current_value'] / $elapsed;
                        $remaining = $m['target_value'] - $m['current_value'];
                        $daysLeft = (int) now()->diffInDays($m['end_date']);

                        return $rate > 0 && ceil($remaining / $rate) > $daysLeft;
                    })
                    ->take(3)
                    ->values(),
            ],
        ];
    }

    /** 3 grouped queries instead of 3 × $days individual queries. */
    private function activity(User $user, CarbonImmutable $start, int $days): array
    {
        $from = $start->toDateString();
        $to = $start->addDays($days - 1)->toDateString();

        $work = $user->workLogs()
            ->selectRaw('DATE(date) as d, COUNT(*) as n')
            ->whereDate('date', '>=', $from)->whereDate('date', '<=', $to)
            ->groupByRaw('DATE(date)')->pluck('n', 'd');

        $learning = $user->learningEntries()
            ->selectRaw('DATE(date) as d, COUNT(*) as n')
            ->whereDate('date', '>=', $from)->whereDate('date', '<=', $to)
            ->groupByRaw('DATE(date)')->pluck('n', 'd');

        $progress = $user->dailyProgressEntries()
            ->selectRaw('DATE(date) as d, COUNT(*) as n')
            ->whereDate('date', '>=', $from)->whereDate('date', '<=', $to)
            ->groupByRaw('DATE(date)')->pluck('n', 'd');

        return collect(range(0, $days - 1))->map(function (int $offset) use ($start, $work, $learning, $progress) {
            $date = $start->addDays($offset)->toDateString();

            return [
                'date' => $date,
                'work' => (int) ($work[$date] ?? 0),
                'learning' => (int) ($learning[$date] ?? 0),
                'progress' => (int) ($progress[$date] ?? 0),
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
