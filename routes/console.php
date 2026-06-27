<?php

use App\Models\User;
use App\Services\BackupExportService;
use App\Services\MilestoneRecalculationService;
use App\Services\NotificationService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('backups:run-due', function (BackupExportService $exports) {
    $count = $exports->runDue();
    $this->info("Processed {$count} backup sync(s).");
})->purpose('Run due ProgressOS backup syncs');

Artisan::command('notifications:generate', function (NotificationService $notifs) {
    $count = 0;
    User::query()->lazyById()->each(function (User $user) use ($notifs, &$count) {
        $count += $notifs->generateOverdueTaskNotifications($user);
        $count += $notifs->generateDueSoonNotifications($user);
        $count += $notifs->generateHabitReminderNotifications($user);
    });
    $this->info("Generated {$count} notification(s).");
})->purpose('Generate overdue, due-soon, and habit reminder notifications for all users');

Artisan::command('milestones:recalculate', function (MilestoneRecalculationService $service) {
    $count = 0;
    User::query()->lazyById()->each(function (User $user) use ($service, &$count) {
        $count += $service->recalculateForUser($user);
    });
    $this->info("Updated {$count} milestone(s).");
})->purpose('Recalculate auto-tracked milestone current values');

Schedule::command('backups:run-due')->hourly();
Schedule::command('notifications:generate')->dailyAt('08:00');
Schedule::command('notifications:send-digest')->dailyAt('08:05');
Schedule::command('milestones:recalculate')->dailyAt('06:00');
