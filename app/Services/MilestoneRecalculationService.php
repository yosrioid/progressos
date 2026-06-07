<?php

namespace App\Services;

use App\Models\Milestone;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

class MilestoneRecalculationService
{
    public function recalculateForUser(User $user): int
    {
        $updated = 0;

        /** @var Collection<int, Milestone> $milestones */
        $milestones = $user->milestones()
            ->whereIn('status', ['active', 'paused'])
            ->where('source_type', '!=', 'manual')
            ->get();

        foreach ($milestones as $milestone) {
            $value = $this->calculateCurrentValue($user, $milestone);
            if ($value === null) {
                continue;
            }
            if ((float) $value !== (float) $milestone->current_value) {
                $milestone->update(['current_value' => $value]);
                $updated++;
            }
        }

        return $updated;
    }

    private function calculateCurrentValue(User $user, Milestone $milestone): ?float
    {
        $since = $milestone->start_date?->toDateString();

        return match ($milestone->source_type) {
            'work_log_count' => (float) $user->workLogs()
                ->when($since, fn ($q) => $q->whereDate('date', '>=', $since))
                ->count(),
            'work_log_minutes' => (float) $user->workLogs()
                ->when($since, fn ($q) => $q->whereDate('date', '>=', $since))
                ->sum('actual_duration'),
            'learning_minutes' => (float) $user->learningEntries()
                ->when($since, fn ($q) => $q->whereDate('date', '>=', $since))
                ->sum('duration_minutes'),
            'task_done_count' => (float) $user->tasks()
                ->where('status', 'done')
                ->when($since, fn ($q) => $q->whereDate('completed_at', '>=', $since))
                ->count(),
            'daily_progress_streak' => (float) $this->getDailyProgressStreak($user),
            default => null,
        };
    }

    private function getDailyProgressStreak(User $user): int
    {
        $dates = $user->dailyProgressEntries()
            ->selectRaw('DATE(date) as d')
            ->distinct()
            ->orderByDesc('d')
            ->pluck('d')
            ->toArray();

        $streak = 0;
        $expected = now()->toDateString();

        foreach ($dates as $date) {
            if ($date === $expected) {
                $streak++;
                $expected = now()->subDays($streak)->toDateString();
            } else {
                break;
            }
        }

        return $streak;
    }
}
