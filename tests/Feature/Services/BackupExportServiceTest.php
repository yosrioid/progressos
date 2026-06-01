<?php

use App\Models\Task;
use App\Models\User;
use App\Services\BackupExportService;
use App\Services\GoogleSheetsBackupService;
use Illuminate\Support\Facades\Storage;

it('runs due backup syncs and exports spreadsheet compatible csv files', function () {
    Storage::fake('local');
    $user = User::factory()->create();
    $connection = $user->backupConnections()->create([
        'provider' => 'google_sheets',
        'name' => 'Personal Sheets',
        'spreadsheet_id' => 'sheet_123',
        'credentials' => [
            'project_id' => 'progressos',
            'client_email' => 'backup@progressos.iam.gserviceaccount.com',
            'private_key' => 'secret',
        ],
        'status' => 'verified',
    ]);
    $this->mock(GoogleSheetsBackupService::class)
        ->shouldReceive('append')
        ->once()
        ->withArgs(fn ($actualConnection, string $sheetName, array $rows) => $actualConnection->is($connection) && $sheetName === 'tasks_daily' && count($rows) === 2)
        ->andReturn('https://docs.google.com/spreadsheets/d/sheet_123/edit');
    Task::factory()->for($user)->create(['title' => 'Export task']);
    $sync = $user->backupSyncs()->create([
        'backup_connection_id' => $connection->id,
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
    Storage::assertExists(str($sync->runs()->first()->file_path)->after('local CSV: ')->toString());
});
