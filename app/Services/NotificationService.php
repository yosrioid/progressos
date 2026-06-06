<?php

namespace App\Services;

use App\Models\InAppNotification;
use App\Models\User;

class NotificationService
{
    public function generateOverdueTaskNotifications(User $user): int
    {
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
                InAppNotification::create([
                    'user_id' => $user->id,
                    'type' => 'task_overdue',
                    'title' => 'Task terlambat',
                    'body' => $task->title.' sudah melewati deadline ('.$task->due_date->format('d M').')',
                    'action_url' => '/tasks/'.$task->id,
                    'data' => ['task_id' => $task->id, 'priority' => $task->priority],
                ]);
                $created++;
            }
        }

        return $created;
    }

    public function generateDueSoonNotifications(User $user): int
    {
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
                InAppNotification::create([
                    'user_id' => $user->id,
                    'type' => 'task_due_soon',
                    'title' => 'Task jatuh tempo besok',
                    'body' => $task->title,
                    'action_url' => '/tasks/'.$task->id,
                    'data' => ['task_id' => $task->id, 'priority' => $task->priority],
                ]);
                $created++;
            }
        }

        return $created;
    }

    public function notifyMilestoneCompleted(User $user, int $milestoneId, string $milestoneTitle): void
    {
        InAppNotification::create([
            'user_id' => $user->id,
            'type' => 'milestone_completed',
            'title' => 'Milestone tercapai! 🎉',
            'body' => $milestoneTitle,
            'action_url' => '/milestones/'.$milestoneId,
            'data' => ['milestone_id' => $milestoneId],
        ]);
    }

    public function notifyHabitStreak(User $user, int $habitId, string $habitName, int $streak): void
    {
        if (! in_array($streak, [7, 14, 21, 30, 60, 90, 100, 365])) {
            return;
        }
        InAppNotification::create([
            'user_id' => $user->id,
            'type' => 'habit_streak',
            'title' => "{$streak} hari streak! 🔥",
            'body' => "Kamu sudah {$streak} hari berturut-turut melakukan: {$habitName}",
            'action_url' => '/habits',
            'data' => ['habit_id' => $habitId, 'streak' => $streak],
        ]);
    }
}
