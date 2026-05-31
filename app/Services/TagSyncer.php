<?php

namespace App\Services;

use App\Models\DailyProgressEntry;
use App\Models\DailyProgressTag;
use App\Models\User;
use App\Models\WorkLog;
use App\Models\WorkLogTag;

class TagSyncer
{
    public function daily(DailyProgressEntry $entry, User $user, array $names): void
    {
        $ids = collect($names)->map(fn ($name) => trim(strtolower($name)))->filter()->unique()
            ->map(fn ($name) => DailyProgressTag::firstOrCreate(['user_id' => $user->id, 'name' => $name])->id);

        $entry->tags()->sync($ids);
    }

    public function workLog(WorkLog $workLog, User $user, array $names): void
    {
        $ids = collect($names)->map(fn ($name) => trim(strtolower($name)))->filter()->unique()
            ->map(fn ($name) => WorkLogTag::firstOrCreate(['user_id' => $user->id, 'name' => $name])->id);

        $workLog->tags()->sync($ids);
    }
}
