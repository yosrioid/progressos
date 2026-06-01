<?php

namespace App\Http\Controllers;

use App\Services\ProjectResolver;
use Illuminate\Http\Request;

class QuickCaptureController extends Controller
{
    public function __invoke(Request $request, ProjectResolver $projects)
    {
        $data = $request->validate([
            'type' => ['required', 'in:task,work_log,daily_progress,learning,blocker'],
            'title' => ['required', 'string', 'max:180'],
            'date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
            'project_name' => ['nullable', 'string', 'max:120'],
            'duration_minutes' => ['nullable', 'integer', 'min:1', 'max:10000'],
        ]);

        $date = $data['date'] ?? now($request->user()->timezone)->toDateString();

        match ($data['type']) {
            'task' => $request->user()->tasks()->create([
                'title' => $data['title'],
                'notes' => $data['notes'] ?? null,
                'status' => 'todo',
                'priority' => 'medium',
                'due_date' => $date,
                'project_id' => $projects->resolve($request->user(), $data['project_name'] ?? null)?->id,
            ]),
            'blocker' => $request->user()->tasks()->create([
                'title' => $data['title'],
                'notes' => $data['notes'] ?? null,
                'status' => 'blocked',
                'priority' => 'high',
                'due_date' => $date,
                'project_id' => $projects->resolve($request->user(), $data['project_name'] ?? null)?->id,
            ]),
            'work_log' => $request->user()->workLogs()->create([
                'date' => $date,
                'project_name' => ($data['project_name'] ?? '') ?: 'General',
                'project_id' => $projects->resolve($request->user(), ($data['project_name'] ?? '') ?: 'General')?->id,
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

        return back()->with('success', 'Captured.');
    }
}
