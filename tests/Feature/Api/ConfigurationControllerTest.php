<?php

use App\Models\BackupRun;
use App\Models\Configuration;
use App\Models\LearningEntry;
use App\Models\User;
use App\Models\WorkLog;
use App\Services\GoogleSheetsBackupService;
use Illuminate\Support\Facades\Storage;

it('stores verifies and lists backup configuration', function () {
    $user = User::factory()->create(['role' => 'admin']);

    $this->actingAs($user)->putJson('/api/admin/configuration/settings', [
        'general' => [
            'app_name' => 'ProgressOS Work',
            'project_name' => 'Personal Ops',
            'tagline' => 'Run the week.',
            'timezone' => 'Asia/Jakarta',
        ],
        'appearance' => [
            'theme' => 'dark',
            'favicon_url' => 'https://example.com/favicon.png',
        ],
        'notifications' => [
            'daily_review_enabled' => true,
            'weekly_review_enabled' => false,
        ],
    ])->assertOk()->assertJsonPath('groups.general.app_name', 'ProgressOS Work');

    $this->actingAs($user)->putJson('/api/admin/configuration/backup-connection', [
        'name' => 'Personal Sheets',
        'spreadsheet_id' => 'sheet_123',
        'credentials_json' => json_encode([
            'project_id' => 'progressos',
            'client_email' => 'backup@progressos.iam.gserviceaccount.com',
            'private_key' => '-----BEGIN PRIVATE KEY-----test-----END PRIVATE KEY-----',
        ]),
    ])->assertOk()->assertJsonPath('connection.has_credentials', true);

    $this->actingAs($user)->postJson('/api/admin/configuration/backup-connection/verify')
        ->assertOk()
        ->assertJsonPath('connection.status', 'verified')
        ->assertJsonPath('connection.service_account_email', 'backup@progressos.iam.gserviceaccount.com');

    $this->actingAs($user)->getJson('/api/admin/configuration')
        ->assertOk()
        ->assertJsonPath('configuration.groups.general.project_name', 'Personal Ops')
        ->assertJsonPath('configuration.connection.name', 'Personal Sheets');

    expect(Configuration::query()->whereNull('user_id')->where('group', 'sync')->where('key', 'google_sheets')->exists())->toBeTrue();
});

it('creates updates deletes and runs backup syncs', function () {
    Storage::fake('local');
    $user = User::factory()->create(['role' => 'admin']);
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
        ->withArgs(fn (array $actualConnection, string $sheetName, array $rows) => $actualConnection['spreadsheet_id'] === 'sheet_123' && $sheetName === 'learning_weekly' && count($rows) === 2)
        ->andReturn('https://docs.google.com/spreadsheets/d/sheet_123/edit');
    WorkLog::factory()->for($user)->create(['title' => 'Backup this work', 'project_name' => 'ABC']);
    LearningEntry::factory()->for($user)->create(['topic' => 'Backup learning']);

    $response = $this->actingAs($user)->postJson('/api/admin/configuration/backup-syncs', [
        'module' => 'work_logs',
        'frequency' => 'daily',
        'destination_sheet_name' => 'work_daily',
        'enabled' => true,
    ])->assertCreated()->assertJsonPath('sync.module', 'work_logs');

    $syncId = $response->json('sync.id');

    $this->actingAs($user)->patchJson("/api/admin/configuration/backup-syncs/{$syncId}", [
        'module' => 'learning',
        'frequency' => 'weekly',
        'destination_sheet_name' => 'learning_weekly',
        'enabled' => true,
    ])->assertOk()->assertJsonPath('sync.frequency', 'weekly');

    $run = $this->actingAs($user)->postJson("/api/admin/configuration/backup-syncs/{$syncId}/run")
        ->assertCreated()
        ->assertJsonPath('run.status', 'completed')
        ->json('run');

    expect(BackupRun::query()->where('id', $run['id'])->where('rows_exported', 1)->exists())->toBeTrue();
    Storage::assertExists(str($run['file_path'])->after('local CSV: ')->toString());

    $this->actingAs($user)->deleteJson("/api/admin/configuration/backup-syncs/{$syncId}")->assertNoContent();
    expect(collect(Configuration::getValue(null, 'sync', 'backup_schedules', []))->where('id', $syncId)->isEmpty())->toBeTrue();
});
