<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class QuickCaptureService
{
    public function __construct(private readonly ProjectResolver $projects) {}

    public function capture(User $user, array $data): Model
    {
        return $this->createRecord($user, $data);
    }

    public function captureIdempotently(User $user, array $data, ?string $key): Model
    {
        $key = trim((string) $key);
        if ($key === '') {
            return $this->capture($user, $data);
        }

        $cacheKey = 'quick-capture:'.$user->id.':'.hash('sha256', $key);
        $existing = Cache::get($cacheKey);
        if (is_array($existing) && isset($existing['type'], $existing['id']) && is_a($existing['type'], Model::class, true)) {
            $record = $existing['type']::query()->find($existing['id']);
            if ($record instanceof Model) {
                return $record;
            }
        }

        $record = $this->createRecord($user, $data);
        Cache::put($cacheKey, ['type' => $record::class, 'id' => $record->getKey()], now()->addDay());

        return $record;
    }

    private function createRecord(User $user, array $data): Model
    {
        $date = $data['date'] ?? now($user->timezone)->toDateString();
        $project = $this->projects->resolve($user, $data['project_name'] ?? null);

        return match ($data['type']) {
            'task', 'blocker' => $user->tasks()->create([
                'title' => $data['title'],
                'notes' => $data['notes'] ?? null,
                'status' => $data['type'] === 'blocker' ? 'blocked' : 'todo',
                'priority' => $data['type'] === 'blocker' ? 'high' : 'medium',
                'due_date' => $date,
                'project_id' => $project?->id,
            ]),
            'work_log' => $user->workLogs()->create([
                'date' => $date,
                'project_name' => $project?->name ?: (($data['project_name'] ?? '') ?: 'General'),
                'project_id' => $project?->id,
                'title' => $data['title'],
                'category' => 'other',
                'status' => 'done',
                'priority' => 'medium',
                'description' => $data['notes'] ?? null,
                'actual_duration' => $data['duration_minutes'] ?? null,
            ]),
            'daily_progress' => $user->dailyProgressEntries()->create([
                'date' => $date,
                'title' => $data['title'],
                'notes' => $data['notes'] ?? null,
                'completed_items' => [],
            ]),
            'learning' => $user->learningEntries()->create([
                'date' => $date,
                'topic' => $data['title'],
                'category' => 'other',
                'source_type' => 'practice',
                'duration_minutes' => $data['duration_minutes'] ?? 30,
                'progress_notes' => $data['notes'] ?? null,
            ]),
        };
    }
}
