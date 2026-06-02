<?php

use App\Services\BackupExportService;
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

Schedule::command('backups:run-due')->hourly();
