<?php

namespace App\Jobs;

use App\Models\User;
use App\Services\ReportSnapshotService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class GenerateReportSnapshot implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $userId, public string $period, public ?string $date = null) {}

    public function handle(ReportSnapshotService $snapshots): void
    {
        $user = User::query()->find($this->userId);
        if ($user) {
            $snapshots->store($user, $this->period, $this->date);
        }
    }
}
