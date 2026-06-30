<?php

use App\Models\BackupRun;
use App\Models\Configuration;
use App\Models\Task;
use App\Models\User;
use App\Services\BackupExportService;
use App\Services\GoogleSheetsBackupService;
use Illuminate\Support\Facades\Storage;

it('runs due backup syncs and exports spreadsheet compatible csv files', function () {
    Storage::fake('local');
    $user = User::factory()->create();
    $connection = [
        'provider' => 'google_sheets',
        'name' => 'Personal Sheets',
        'spreadsheet_id' => 'sheet_123',
        'credentials' => [
            'project_id' => 'progressos',
            'client_email' => 'backup@progressos.iam.gserviceaccount.com',
            'private_key' => 'secret',
        ],
        'status' => 'verified',
    ];
    Configuration::setValue(null, 'sync', 'google_sheets', $connection, true);
    $this->mock(GoogleSheetsBackupService::class)
        ->shouldReceive('append')
        ->once()
        ->withArgs(fn (array $actualConnection, string $sheetName, array $rows) => $actualConnection['spreadsheet_id'] === 'sheet_123' && $sheetName === 'tasks_daily' && count($rows) === 2)
        ->andReturn('https://docs.google.com/spreadsheets/d/sheet_123/edit');
    Task::factory()->for($user)->create(['title' => 'Export task']);
    $sync = [
        'id' => 'sync-tasks',
        'module' => 'tasks',
        'frequency' => 'daily',
        'destination_sheet_name' => 'tasks_daily',
        'enabled' => true,
        'next_run_at' => now()->subMinute()->toISOString(),
    ];
    Configuration::setValue(null, 'sync', 'backup_schedules', [$sync]);

    $count = app(BackupExportService::class)->runDue();
    $run = BackupRun::query()->where('sync_id', 'sync-tasks')->first();
    $freshSync = collect(Configuration::getValue(null, 'sync', 'backup_schedules', []))->firstWhere('id', 'sync-tasks');

    expect($count)->toBe(1)
        ->and($freshSync['last_run_at'])->not->toBeNull()
        ->and($run?->rows_exported)->toBe(1);
    Storage::assertExists(str($run?->file_path)->after('local CSV: ')->toString());
});
