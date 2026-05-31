<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DailyProgressEntry;
use App\Models\LearningEntry;
use App\Models\Milestone;
use App\Models\Project;
use App\Models\Task;
use App\Models\WorkLog;
use App\Services\DashboardData;
use App\Services\MilestoneProgressSync;
use App\Services\ProjectResolver;
use App\Services\ReportBuilder;
use App\Services\TagSyncer;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProgressApiController extends Controller
{
    public function dashboard(Request $request, DashboardData $dashboard, MilestoneProgressSync $milestones)
    {
        return response()->json($dashboard->for($request->user(), $milestones));
    }

    public function projects(Request $request)
    {
        return response()->json([
            'projects' => $request->user()->projects()
                ->withCount([
                    'tasks',
                    'tasks as open_tasks_count' => fn ($query) => $query->whereIn('status', ['todo', 'in_progress', 'blocked']),
                    'workLogs',
                ])
                ->where('archived', false)
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function project(Request $request, Project $project)
    {
        abort_unless($project->user_id === $request->user()->id, 403);

        return response()->json([
            'project' => $project,
            'tasks' => $project->tasks()->with('project')->latest()->take(20)->get(),
            'workLogs' => $project->workLogs()->with('tags')->latest('date')->take(20)->get(),
            'metrics' => [
                'open_tasks' => $project->tasks()->whereIn('status', ['todo', 'in_progress', 'blocked'])->count(),
                'completed_tasks' => $project->tasks()->where('status', 'done')->count(),
                'logged_minutes' => $project->workLogs()->sum('actual_duration'),
                'blockers' => $project->tasks()->where('status', 'blocked')->count() + $project->workLogs()->where('status', 'blocked')->count(),
            ],
        ]);
    }

    public function quickCapture(Request $request, ProjectResolver $projects)
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

    public function dailyProgress(Request $request)
    {
        return response()->json(['entries' => DailyProgressEntry::ownedBy($request->user())->with('tags')->latest('date')->paginate(12)]);
    }

    public function storeDailyProgress(Request $request, TagSyncer $tags)
    {
        $data = $request->validate([
            'date' => ['required', 'date'],
            'title' => ['required', 'string', 'max:180'],
            'in_progress' => ['nullable', 'string'],
            'todo' => ['nullable', 'string'],
            'blockers' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
            'mood' => ['nullable', 'string', 'max:80'],
            'completed_items' => ['nullable', 'array'],
            'tags' => ['nullable', 'array'],
        ]);
        $tagNames = $data['tags'] ?? [];
        unset($data['tags']);
        $entry = $request->user()->dailyProgressEntries()->create($data);
        $tags->daily($entry, $request->user(), $tagNames);

        return response()->json(['entry' => $entry->load('tags')], 201);
    }

    public function workLogs(Request $request)
    {
        return response()->json(['logs' => WorkLog::ownedBy($request->user())->with('tags')->latest('date')->paginate(12)]);
    }

    public function storeWorkLog(Request $request, ProjectResolver $projects, TagSyncer $tags)
    {
        $data = $request->validate([
            'date' => ['required', 'date'],
            'project_name' => ['required', 'string', 'max:120'],
            'title' => ['required', 'string', 'max:180'],
            'category' => ['required', Rule::in(WorkLog::CATEGORIES)],
            'status' => ['required', Rule::in(WorkLog::STATUSES)],
            'priority' => ['required', Rule::in(WorkLog::PRIORITIES)],
            'description' => ['nullable', 'string'],
            'actual_duration' => ['nullable', 'integer', 'min:1'],
            'tags' => ['nullable', 'array'],
        ]);
        $tagNames = $data['tags'] ?? [];
        unset($data['tags']);
        $data['project_id'] = $projects->resolve($request->user(), $data['project_name'])?->id;
        $log = $request->user()->workLogs()->create($data);
        $tags->workLog($log, $request->user(), $tagNames);

        return response()->json(['log' => $log->load('tags')], 201);
    }

    public function tasks(Request $request)
    {
        return response()->json(['tasks' => Task::ownedBy($request->user())->with('project')->latest()->paginate(20)]);
    }

    public function storeTask(Request $request)
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:180'],
            'project_id' => ['nullable', 'integer', Rule::exists('projects', 'id')->where('user_id', $request->user()->id)],
            'notes' => ['nullable', 'string'],
            'status' => ['required', Rule::in(Task::STATUSES)],
            'priority' => ['required', Rule::in(Task::PRIORITIES)],
            'due_date' => ['nullable', 'date'],
        ]);
        $data['completed_at'] = $data['status'] === 'done' ? now() : null;

        return response()->json(['task' => $request->user()->tasks()->create($data)], 201);
    }

    public function updateTaskStatus(Request $request, Task $task)
    {
        abort_unless($task->user_id === $request->user()->id, 403);
        $data = $request->validate(['status' => ['required', Rule::in(Task::STATUSES)]]);
        $task->update([...$data, 'completed_at' => $data['status'] === 'done' ? now() : null]);

        return response()->json(['task' => $task->fresh()]);
    }

    public function deleteTask(Request $request, Task $task)
    {
        abort_unless($task->user_id === $request->user()->id, 403);
        $task->delete();

        return response()->noContent();
    }

    public function learning(Request $request)
    {
        return response()->json(['entries' => LearningEntry::ownedBy($request->user())->latest('date')->paginate(12)]);
    }

    public function storeLearning(Request $request)
    {
        $data = $request->validate([
            'date' => ['required', 'date'],
            'topic' => ['required', 'string', 'max:180'],
            'category' => ['required', Rule::in(LearningEntry::CATEGORIES)],
            'source_type' => ['required', Rule::in(LearningEntry::SOURCE_TYPES)],
            'duration_minutes' => ['required', 'integer', 'min:1'],
            'progress_notes' => ['nullable', 'string'],
            'takeaway' => ['nullable', 'string'],
            'next_action' => ['nullable', 'string'],
            'rating' => ['nullable', 'integer', 'min:1', 'max:5'],
        ]);

        return response()->json(['entry' => $request->user()->learningEntries()->create($data)], 201);
    }

    public function milestones(Request $request)
    {
        return response()->json(['milestones' => Milestone::ownedBy($request->user())->latest()->paginate(20)]);
    }

    public function storeMilestone(Request $request)
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:180'],
            'category' => ['required', 'string', 'max:120'],
            'target_type' => ['required', Rule::in(Milestone::TARGET_TYPES)],
            'source_type' => ['nullable', Rule::in(Milestone::SOURCE_TYPES)],
            'source_filter' => ['nullable', 'string', 'max:180'],
            'target_value' => ['required', 'numeric', 'min:0'],
            'current_value' => ['nullable', 'numeric', 'min:0'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'status' => ['required', Rule::in(Milestone::STATUSES)],
            'notes' => ['nullable', 'string'],
        ]);
        $data['source_type'] ??= 'manual';
        $data['current_value'] ??= 0;

        return response()->json(['milestone' => $request->user()->milestones()->create($data)], 201);
    }

    public function report(Request $request, ReportBuilder $builder, string $period)
    {
        abort_unless(in_array($period, ['weekly', 'monthly'], true), 404);

        return response()->json(['report' => $builder->build($request->user(), $period, $request->query('date'))]);
    }

    public function exportReport(Request $request, ReportBuilder $builder, string $period): StreamedResponse
    {
        abort_unless(in_array($period, ['weekly', 'monthly'], true), 404);
        $report = $builder->build($request->user(), $period, $request->query('date'));

        return response()->streamDownload(function () use ($report) {
            $out = fopen('php://output', 'w');
            $write = fn (array $row) => fputcsv($out, array_map([$this, 'csvSafe'], $row));
            $write(['metric', 'label', 'value']);
            $write(['period', '', $report['start'].' to '.$report['end']]);
            $write(['completed_work_logs', '', count($report['completed_work_logs'])]);
            $write(['open_blockers', '', count($report['open_blockers'])]);
            $write(['learning_minutes', '', $report['learning_totals']['minutes']]);
            foreach ($report['time_by_category'] as $category => $minutes) {
                $write(['work_minutes', $category, $minutes]);
            }
            fclose($out);
        }, "progressos-{$period}-{$report['start']}.csv", ['Content-Type' => 'text/csv']);
    }

    public function search(Request $request)
    {
        $q = trim((string) $request->query('q'));
        if ($q === '') {
            return response()->json(['query' => '', 'results' => []]);
        }

        return response()->json([
            'query' => $q,
            'results' => [
                'daily_progress' => $request->user()->dailyProgressEntries()->where('title', 'like', "%{$q}%")->take(8)->get(),
                'work_logs' => $request->user()->workLogs()->where('title', 'like', "%{$q}%")->take(8)->get(),
                'learning' => $request->user()->learningEntries()->where('topic', 'like', "%{$q}%")->take(8)->get(),
                'milestones' => $request->user()->milestones()->where('title', 'like', "%{$q}%")->take(8)->get(),
            ],
        ]);
    }

    private function csvSafe(mixed $value): mixed
    {
        if (! is_string($value)) {
            return $value;
        }

        return preg_match('/^[=+\-@]/', $value) ? "'".$value : $value;
    }
}
