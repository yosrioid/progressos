<?php

use App\Models\BackupRun;
use App\Models\BackupSync;
use App\Models\LearningEntry;
use App\Models\User;
use App\Models\WorkLog;
use Illuminate\Support\Facades\Storage;

it('stores verifies and lists backup configuration', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->putJson('/api/v1/configuration/backup-connection', [
        'name' => 'Personal Sheets',
        'spreadsheet_id' => 'sheet_123',
        'credentials_json' => json_encode([
            'project_id' => 'progressos',
            'client_email' => 'backup@progressos.iam.gserviceaccount.com',
            'private_key' => '-----BEGIN PRIVATE KEY-----test-----END PRIVATE KEY-----',
        ]),
    ])->assertOk()->assertJsonPath('connection.has_credentials', true);

    $this->actingAs($user)->postJson('/api/v1/configuration/backup-connection/verify')
        ->assertOk()
        ->assertJsonPath('connection.status', 'verified')
        ->assertJsonPath('connection.service_account_email', 'backup@progressos.iam.gserviceaccount.com');

    $this->actingAs($user)->getJson('/api/v1/configuration')
        ->assertOk()
        ->assertJsonPath('configuration.connection.name', 'Personal Sheets');
});

it('creates updates deletes and runs backup syncs', function () {
    Storage::fake('local');
    $user = User::factory()->create();
    WorkLog::factory()->for($user)->create(['title' => 'Backup this work', 'project_name' => 'ABC']);
    LearningEntry::factory()->for($user)->create(['topic' => 'Backup learning']);

    $response = $this->actingAs($user)->postJson('/api/v1/configuration/backup-syncs', [
        'module' => 'work_logs',
        'frequency' => 'daily',
        'destination_sheet_name' => 'work_daily',
        'enabled' => true,
    ])->assertCreated()->assertJsonPath('sync.module', 'work_logs');

    $syncId = $response->json('sync.id');

    $this->actingAs($user)->patchJson("/api/v1/configuration/backup-syncs/{$syncId}", [
        'module' => 'learning',
        'frequency' => 'weekly',
        'destination_sheet_name' => 'learning_weekly',
        'enabled' => true,
    ])->assertOk()->assertJsonPath('sync.frequency', 'weekly');

    $run = $this->actingAs($user)->postJson("/api/v1/configuration/backup-syncs/{$syncId}/run")
        ->assertCreated()
        ->assertJsonPath('run.status', 'completed')
        ->json('run');

    expect(BackupRun::query()->where('id', $run['id'])->where('rows_exported', 1)->exists())->toBeTrue();
    Storage::assertExists($run['file_path']);

    $this->actingAs($user)->deleteJson("/api/v1/configuration/backup-syncs/{$syncId}")->assertNoContent();
    expect(BackupSync::query()->find($syncId))->toBeNull();
});
