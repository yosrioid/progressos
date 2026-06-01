<?php

namespace App\Support;

use App\Models\DailyProgressEntry;
use App\Models\LearningEntry;
use App\Models\Milestone;
use App\Models\Task;
use App\Models\WorkLog;

class ReferenceTypes
{
    public const MAP = [
        'task' => Task::class,
        'work_log' => WorkLog::class,
        'learning' => LearningEntry::class,
        'milestone' => Milestone::class,
        'daily_progress' => DailyProgressEntry::class,
    ];
}
