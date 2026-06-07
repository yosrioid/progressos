<?php

namespace App\Services;

use App\Models\InAppNotification;
use App\Models\Task;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class NotificationService
{
    public function generateOverdueTaskNotifications(User $user): int
    {
        /** @var \Illuminate\Database\Eloquent\Collection<int, Task> $overdue */
        $overdue = $user->tasks()
            ->whereNotIn('status', ['done', 'cancelled'])
            ->whereNotNull('due_date')
            ->whereDate('due_date', '<', today())
            ->get(['id', 'title', 'due_date', 'priority']);

        $created = 0;
        foreach ($overdue as $task) {
            $exists = InAppNotification::where('user_id', $user->id)
                ->where('type', 'task_overdue')
                ->where('data->task_id', $task->id)
                ->whereDate('created_at', today())
                ->exists();

            if (! $exists) {
                try {
                    InAppNotification::create([
                        'user_id' => $user->id,
                        'type' => 'task_overdue',
                        'title' => 'Task terlambat',
                        'body' => $task->title.' sudah melewati deadline ('.$task->due_date->format('d M').')',
                        'action_url' => '/tasks/'.$task->id,
                        'data' => ['task_id' => $task->id, 'priority' => $task->priority],
                    ]);
                    $created++;
                } catch (\Throwable $e) {
                    Log::error('Failed to create overdue notification', ['task_id' => $task->id, 'error' => $e->getMessage()]);
                }
            }
        }

        return $created;
    }

    public function generateDueSoonNotifications(User $user): int
    {
        /** @var \Illuminate\Database\Eloquent\Collection<int, Task> $dueSoon */
        $dueSoon = $user->tasks()
            ->whereNotIn('status', ['done', 'cancelled'])
            ->whereNotNull('due_date')
            ->whereDate('due_date', today()->addDay())
            ->get(['id', 'title', 'due_date', 'priority']);

        $created = 0;
        foreach ($dueSoon as $task) {
            $exists = InAppNotification::where('user_id', $user->id)
                ->where('type', 'task_due_soon')
                ->where('data->task_id', $task->id)
                ->whereDate('created_at', today())
                ->exists();

            if (! $exists) {
                try {
                    InAppNotification::create([
                        'user_id' => $user->id,
                        'type' => 'task_due_soon',
                        'title' => 'Task jatuh tempo besok',
                        'body' => $task->title,
                        'action_url' => '/tasks/'.$task->id,
                        'data' => ['task_id' => $task->id, 'priority' => $task->priority],
                    ]);
                    $created++;
                } catch (\Throwable $e) {
                    Log::error('Failed to create due-soon notification', ['task_id' => $task->id, 'error' => $e->getMessage()]);
                }
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
                'title' => 'Milestone tercapai!',
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
                'title' => "{$streak} hari streak!",
                'body' => "Kamu sudah {$streak} hari berturut-turut melakukan: {$habitName}",
                'action_url' => '/habits',
                'data' => ['habit_id' => $habitId, 'streak' => $streak],
            ]);
        } catch (\Throwable $e) {
            Log::error('Failed to create habit streak notification', ['habit_id' => $habitId, 'error' => $e->getMessage()]);
        }
    }
}
