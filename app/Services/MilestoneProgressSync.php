<?php

namespace App\Services;

use App\Models\Milestone;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\App;

class MilestoneProgressSync
{
    public function syncFor(User $user): void
    {
        $user->milestones()->where('status', 'active')->get()->each(function (Milestone $milestone) use ($user) {
            if ($milestone->source_type === 'manual') {
                return;
            }

            $newValue = $this->calculate($user, $milestone);
            $justCompleted = (float) $milestone->current_value < (float) $milestone->target_value
                && $newValue >= (float) $milestone->target_value
                && is_null($milestone->completed_at);
            $milestone->update([
                'current_value' => $newValue,
                'completed_at' => $justCompleted ? now() : $milestone->completed_at,
            ]);
            if ($justCompleted) {
                App::make(NotificationService::class)->notifyMilestoneCompleted($user, $milestone->id, $milestone->title);
            }
        });
    }

    public function calculate(User $user, Milestone $milestone): int|float
    {
        $filter = trim((string) $milestone->source_filter);

        return match ($milestone->source_type) {
            'work_log_count' => $user->workLogs()->when($filter, fn ($q) => $q->where('project_name', $filter)->orWhere('category', $filter)->orWhere('status', $filter))->count(),
            'work_log_minutes' => $user->workLogs()->when($filter, fn ($q) => $q->where('project_name', $filter)->orWhere('category', $filter))->sum('actual_duration'),
            'learning_minutes' => $user->learningEntries()->when($filter, fn ($q) => $q->where('category', $filter)->orWhere('topic', 'like', "%{$filter}%"))->sum('duration_minutes'),
            'daily_progress_streak' => $this->streak($user->dailyProgressEntries()->pluck('date')->map->toDateString()->all()),
            'task_done_count' => $user->tasks()->where('status', 'done')->when($filter, fn ($q) => $q->where('priority', $filter))->count(),
            default => (float) $milestone->current_value,
        };
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
