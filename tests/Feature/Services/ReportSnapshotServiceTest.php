<?php

use App\Models\User;
use App\Models\WorkLog;
use App\Services\ReportSnapshotService;

it('upserts report snapshots by user period and start date', function () {
    $user = User::factory()->create();
    WorkLog::factory()->for($user)->create(['date' => '2026-06-01', 'status' => 'done']);
    $service = app(ReportSnapshotService::class);

    $first = $service->store($user, 'weekly', '2026-06-01');
    $second = $service->store($user, 'weekly', '2026-06-01');

    expect($second->id)->toBe($first->id)
        ->and($user->reportSnapshots()->count())->toBe(1);
});
