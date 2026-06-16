<?php

namespace App\Services;

use App\Models\Habit;
use App\Models\InAppNotification;
use App\Models\Task;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Log;

class NotificationService
{
    public function generateOverdueTaskNotifications(User $user): int
    {
        /** @var Collection<int, Task> $overdue */
        $overdue = $user->tasks()
            ->whereNotIn('status', ['done', 'cancelled'])
            ->whereNotNull('due_date')
            ->whereDate('due_date', '<', today())
            ->get(['id', 'title', 'due_date', 'priority']);

        $notifiedToday = InAppNotification::where('user_id', $user->id)
            ->where('type', 'task_overdue')
            ->whereDate('created_at', today())
            ->get(['data'])
            ->map(fn ($n) => $n->data['task_id'] ?? null)
            ->filter()
            ->flip()
            ->all();

        $created = 0;
        foreach ($overdue as $task) {
            if (isset($notifiedToday[$task->id])) {
                continue;
            }

            try {
                InAppNotification::create([
                    'user_id' => $user->id,
                    'type' => 'task_overdue',
                    'title' => 'Task overdue',
                    'body' => $task->title.' passed its deadline ('.$task->due_date->format('M d').')',
                    'action_url' => '/tasks/'.$task->id,
                    'data' => ['task_id' => $task->id, 'priority' => $task->priority],
                ]);
                $created++;
            } catch (\Throwable $e) {
                Log::error('Failed to create overdue notification', ['task_id' => $task->id, 'error' => $e->getMessage()]);
            }
        }

        return $created;
    }

    public function generateDueSoonNotifications(User $user): int
    {
        /** @var Collection<int, Task> $dueSoon */
        $dueSoon = $user->tasks()
            ->whereNotIn('status', ['done', 'cancelled'])
            ->whereNotNull('due_date')
            ->whereDate('due_date', today()->addDay())
            ->get(['id', 'title', 'due_date', 'priority']);

        $notifiedToday = InAppNotification::where('user_id', $user->id)
            ->where('type', 'task_due_soon')
            ->whereDate('created_at', today())
            ->get(['data'])
            ->map(fn ($n) => $n->data['task_id'] ?? null)
            ->filter()
            ->flip()
            ->all();

        $created = 0;
        foreach ($dueSoon as $task) {
            if (isset($notifiedToday[$task->id])) {
                continue;
            }

            try {
                InAppNotification::create([
                    'user_id' => $user->id,
                    'type' => 'task_due_soon',
                    'title' => 'Task due tomorrow',
                    'body' => $task->title,
                    'action_url' => '/tasks/'.$task->id,
                    'data' => ['task_id' => $task->id, 'priority' => $task->priority],
                ]);
                $created++;
            } catch (\Throwable $e) {
                Log::error('Failed to create due-soon notification', ['task_id' => $task->id, 'error' => $e->getMessage()]);
            }
        }

        return $created;
    }

    public function generateHabitReminderNotifications(User $user): int
    {
        $today = today()->toDateString();

        /** @var Collection<int, Habit> $unlogged */
        $unlogged = $user->habits()
            ->where('active', true)
            ->whereDoesntHave('logs', fn ($q) => $q->whereDate('date', $today))
            ->get(['id', 'name', 'icon', 'frequency', 'target_days'])
            ->filter(function (Habit $habit) {
                if ($habit->frequency === 'daily') {
                    return true;
                }
                if ($habit->frequency === 'weekly' && is_array($habit->target_days)) {
                    return in_array((int) now()->dayOfWeek, $habit->target_days, true);
                }

                return false;
            });

        $notifiedHabitIds = InAppNotification::where('user_id', $user->id)
            ->where('type', 'habit_reminder')
            ->whereDate('created_at', $today)
            ->get(['data'])
            ->map(fn ($n) => $n->data['habit_id'] ?? null)
            ->filter()
            ->flip()
            ->all();

        $created = 0;
        foreach ($unlogged as $habit) {
            if (isset($notifiedHabitIds[$habit->id])) {
                continue;
            }

            try {
                InAppNotification::create([
                    'user_id' => $user->id,
                    'type' => 'habit_reminder',
                    'title' => 'Habit reminder',
                    'body' => ($habit->icon ? $habit->icon.' ' : '').$habit->name.' not logged yet today.',
                    'action_url' => '/habits',
                    'data' => ['habit_id' => $habit->id],
                ]);
                $created++;
            } catch (\Throwable $e) {
                Log::error('Failed to create habit reminder notification', ['habit_id' => $habit->id, 'error' => $e->getMessage()]);
            }
        }

        return $created;
    }

    public function notifyMilestoneCompleted(User $user, int $milestoneId, string $milestoneTitle): void
    {
        try {
            InAppNotification::create([
                'user_id' => $user->id,
                'type' => 'milestone_completed',
                'title' => 'Milestone completed!',
                'body' => $milestoneTitle,
                'action_url' => '/milestones/'.$milestoneId,
                'data' => ['milestone_id' => $milestoneId],
            ]);
        } catch (\Throwable $e) {
            Log::error('Failed to create milestone notification', ['milestone_id' => $milestoneId, 'error' => $e->getMessage()]);
        }
    }

    public function notifyHabitStreak(User $user, int $habitId, string $habitName, int $streak): void
    {
        if (! in_array($streak, [7, 14, 21, 30, 60, 90, 100, 365])) {
            return;
        }
        try {
            InAppNotification::create([
                'user_id' => $user->id,
                'type' => 'habit_streak',
                'title' => "{$streak}-day streak!",
                'body' => "You've maintained a {$streak}-day streak for: {$habitName}",
                'action_url' => '/habits',
                'data' => ['habit_id' => $habitId, 'streak' => $streak],
            ]);
        } catch (\Throwable $e) {
            Log::error('Failed to create habit streak notification', ['habit_id' => $habitId, 'error' => $e->getMessage()]);
        }
    }
}
