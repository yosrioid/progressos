<?php

use App\Models\Task;
use App\Models\User;
use App\Services\BackupExportService;
use Illuminate\Support\Facades\Storage;

it('runs due backup syncs and exports spreadsheet compatible csv files', function () {
    Storage::fake('local');
    $user = User::factory()->create();
    Task::factory()->for($user)->create(['title' => 'Export task']);
    $sync = $user->backupSyncs()->create([
        'module' => 'tasks',
        'frequency' => 'daily',
        'destination_sheet_name' => 'tasks_daily',
        'enabled' => true,
        'next_run_at' => now()->subMinute(),
    ]);

    $count = app(BackupExportService::class)->runDue();

    expect($count)->toBe(1)
        ->and($sync->fresh()->last_run_at)->not->toBeNull()
        ->and($sync->runs()->first()->rows_exported)->toBe(1);
    Storage::assertExists($sync->runs()->first()->file_path);
});
