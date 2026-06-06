<?php

use App\Models\User;
use App\Services\BackupExportService;
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
    User::all()->each(function (User $user) use ($notifs, &$count) {
        $count += $notifs->generateOverdueTaskNotifications($user);
        $count += $notifs->generateDueSoonNotifications($user);
    });
    $this->info("Generated {$count} notification(s).");
})->purpose('Generate overdue and due-soon task notifications for all users');

Schedule::command('backups:run-due')->hourly();
Schedule::command('notifications:generate')->dailyAt('08:00');
