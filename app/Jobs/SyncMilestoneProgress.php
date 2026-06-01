<?php

namespace App\Jobs;

use App\Models\User;
use App\Services\MilestoneProgressSync;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SyncMilestoneProgress implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $userId) {}

    public function handle(MilestoneProgressSync $sync): void
    {
        $user = User::query()->find($this->userId);
        if ($user) {
            $sync->syncFor($user);
        }
    }
}
