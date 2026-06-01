<?php

use App\Models\User;
use App\Models\WorkLog;
use App\Services\QuickCaptureService;

it('creates a project-linked work log from quick capture data', function () {
    $user = User::factory()->create();
    $service = app(QuickCaptureService::class);

    $record = $service->capture($user, [
        'type' => 'work_log',
        'title' => 'Service capture',
        'project_name' => 'ABC',
        'duration_minutes' => 30,
    ]);

    expect($record)->toBeInstanceOf(WorkLog::class)
        ->and($record->project_name)->toBe('ABC')
        ->and($record->project_id)->not->toBeNull();
});
