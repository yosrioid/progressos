<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\QuickCaptureRequest;
use App\Services\ProjectResolver;

class CaptureController extends Controller
{
    public function __invoke(QuickCaptureRequest $request, ProjectResolver $projects)
    {
        $data = $request->validated();
        $date = $data['date'] ?? now($request->user()->timezone)->toDateString();
        $project = $projects->resolve($request->user(), $data['project_name'] ?? null);

        $record = match ($data['type']) {
            'task', 'blocker' => $request->user()->tasks()->create([
                'title' => $data['title'],
                'notes' => $data['notes'] ?? null,
                'status' => $data['type'] === 'blocker' ? 'blocked' : 'todo',
                'priority' => $data['type'] === 'blocker' ? 'high' : 'medium',
                'due_date' => $date,
                'project_id' => $project?->id,
            ]),
            'work_log' => $request->user()->workLogs()->create([
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
            'daily_progress' => $request->user()->dailyProgressEntries()->create([
                'date' => $date,
                'title' => $data['title'],
                'notes' => $data['notes'] ?? null,
                'completed_items' => [],
            ]),
            'learning' => $request->user()->learningEntries()->create([
                'date' => $date,
                'topic' => $data['title'],
                'category' => 'other',
                'source_type' => 'practice',
                'duration_minutes' => $data['duration_minutes'] ?? 30,
                'progress_notes' => $data['notes'] ?? null,
            ]),
        };

        return response()->json(['record' => $record], 201);
    }
}
