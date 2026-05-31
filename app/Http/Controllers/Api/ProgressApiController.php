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

    public function updateProject(Request $request, Project $project)
    {
        abort_unless($project->user_id === $request->user()->id, 403);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'color' => ['nullable', 'string', 'max:32'],
            'archived' => ['boolean'],
        ]);
        $project->update($data);

        return response()->json(['project' => $project->fresh()]);
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
        $query = DailyProgressEntry::ownedBy($request->user())->with('tags');
        $this->applyTextSearch($query, $request, ['title', 'notes', 'in_progress', 'todo', 'blockers']);
        if ($request->filled('from')) {
            $query->whereDate('date', '>=', $request->query('from'));
        }
        if ($request->filled('to')) {
            $query->whereDate('date', '<=', $request->query('to'));
        }
        if ($request->filled('status')) {
            $query->where('mood', $request->query('status'));
        }
        if ($request->filled('tag')) {
            $query->whereHas('tags', fn ($tags) => $tags->where('name', $request->query('tag')));
        }
        if (! $request->boolean('archived')) {
            $query->where('archived', false);
        }

        return response()->json(['entries' => $this->paginateSorted($query, $request, 'date', 12, ['date', 'created_at', 'updated_at', 'title'])]);
    }

    public function showDailyProgress(Request $request, DailyProgressEntry $dailyProgress)
    {
        abort_unless($dailyProgress->user_id === $request->user()->id, 403);

        return response()->json(['entry' => $dailyProgress->load('tags')]);
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

    public function updateDailyProgress(Request $request, DailyProgressEntry $dailyProgress, TagSyncer $tags)
    {
        abort_unless($dailyProgress->user_id === $request->user()->id, 403);
        $data = $request->validate($this->dailyProgressRules());
        $tagNames = $data['tags'] ?? [];
        unset($data['tags']);
        $dailyProgress->update($data);
        $tags->daily($dailyProgress, $request->user(), $tagNames);

        return response()->json(['entry' => $dailyProgress->fresh()->load('tags')]);
    }

    public function deleteDailyProgress(Request $request, DailyProgressEntry $dailyProgress)
    {
        abort_unless($dailyProgress->user_id === $request->user()->id, 403);
        $dailyProgress->delete();

        return response()->noContent();
    }

    public function workLogs(Request $request)
    {
        $query = WorkLog::ownedBy($request->user())->with('tags');
        $this->applyTextSearch($query, $request, ['title', 'project_name', 'ticket_code', 'description', 'resolution_or_outcome']);
        foreach (['status', 'category', 'priority', 'project_name'] as $filter) {
            if ($request->filled($filter)) {
                $query->where($filter, $request->query($filter));
            }
        }
        if ($request->filled('from')) {
            $query->whereDate('date', '>=', $request->query('from'));
        }
        if ($request->filled('to')) {
            $query->whereDate('date', '<=', $request->query('to'));
        }
        if ($request->filled('tag')) {
            $query->whereHas('tags', fn ($tags) => $tags->where('name', $request->query('tag')));
        }

        return response()->json(['logs' => $this->paginateSorted($query, $request, 'date', 12, ['date', 'created_at', 'updated_at', 'title', 'status', 'priority', 'category'])]);
    }

    public function showWorkLog(Request $request, WorkLog $workLog)
    {
        abort_unless($workLog->user_id === $request->user()->id, 403);

        return response()->json(['log' => $workLog->load('tags')]);
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

    public function updateWorkLog(Request $request, WorkLog $workLog, ProjectResolver $projects, TagSyncer $tags)
    {
        abort_unless($workLog->user_id === $request->user()->id, 403);
        $data = $request->validate($this->workLogRules());
        $tagNames = $data['tags'] ?? [];
        unset($data['tags']);
        $data['project_id'] = $projects->resolve($request->user(), $data['project_name'])?->id;
        $workLog->update($data);
        $tags->workLog($workLog, $request->user(), $tagNames);

        return response()->json(['log' => $workLog->fresh()->load('tags')]);
    }

    public function deleteWorkLog(Request $request, WorkLog $workLog)
    {
        abort_unless($workLog->user_id === $request->user()->id, 403);
        $workLog->delete();

        return response()->noContent();
    }

    public function tasks(Request $request)
    {
        $query = Task::ownedBy($request->user())->with('project');
        $this->applyTextSearch($query, $request, ['title', 'notes']);
        foreach (['status', 'priority', 'project_id'] as $filter) {
            if ($request->filled($filter)) {
                $query->where($filter, $request->query($filter));
            }
        }
        if ($request->filled('from')) {
            $query->whereDate('due_date', '>=', $request->query('from'));
        }
        if ($request->filled('to')) {
            $query->whereDate('due_date', '<=', $request->query('to'));
        }

        return response()->json(['tasks' => $this->paginateSorted($query, $request, 'created_at', 20, ['created_at', 'updated_at', 'due_date', 'title', 'status', 'priority'])]);
    }

    public function showTask(Request $request, Task $task)
    {
        abort_unless($task->user_id === $request->user()->id, 403);

        return response()->json(['task' => $task->load('project')]);
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

    public function updateTask(Request $request, Task $task)
    {
        abort_unless($task->user_id === $request->user()->id, 403);
        $data = $request->validate($this->taskRules($request));
        $data['completed_at'] = $data['status'] === 'done' ? ($task->completed_at ?? now()) : null;
        $task->update($data);

        return response()->json(['task' => $task->fresh()->load('project')]);
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
        $query = LearningEntry::ownedBy($request->user());
        $this->applyTextSearch($query, $request, ['topic', 'progress_notes', 'takeaway', 'next_action']);
        foreach (['category', 'source_type'] as $filter) {
            if ($request->filled($filter)) {
                $query->where($filter, $request->query($filter));
            }
        }
        if ($request->filled('from')) {
            $query->whereDate('date', '>=', $request->query('from'));
        }
        if ($request->filled('to')) {
            $query->whereDate('date', '<=', $request->query('to'));
        }

        return response()->json(['entries' => $this->paginateSorted($query, $request, 'date', 12, ['date', 'created_at', 'updated_at', 'topic', 'category'])]);
    }

    public function showLearning(Request $request, LearningEntry $learning)
    {
        abort_unless($learning->user_id === $request->user()->id, 403);

        return response()->json(['entry' => $learning]);
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

    public function updateLearning(Request $request, LearningEntry $learning)
    {
        abort_unless($learning->user_id === $request->user()->id, 403);
        $learning->update($request->validate($this->learningRules()));

        return response()->json(['entry' => $learning->fresh()]);
    }

    public function deleteLearning(Request $request, LearningEntry $learning)
    {
        abort_unless($learning->user_id === $request->user()->id, 403);
        $learning->delete();

        return response()->noContent();
    }

    public function milestones(Request $request)
    {
        $query = Milestone::ownedBy($request->user());
        $this->applyTextSearch($query, $request, ['title', 'category', 'notes']);
        foreach (['status', 'target_type', 'category'] as $filter) {
            if ($request->filled($filter)) {
                $query->where($filter, $request->query($filter));
            }
        }

        return response()->json(['milestones' => $this->paginateSorted($query, $request, 'created_at', 20, ['created_at', 'updated_at', 'end_date', 'title', 'status', 'category'])]);
    }

    public function showMilestone(Request $request, Milestone $milestone)
    {
        abort_unless($milestone->user_id === $request->user()->id, 403);

        return response()->json(['milestone' => $milestone->setAttribute('progress_percent', $milestone->progressPercent())]);
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

    public function updateMilestone(Request $request, Milestone $milestone)
    {
        abort_unless($milestone->user_id === $request->user()->id, 403);
        $data = $request->validate($this->milestoneRules());
        $data['source_type'] ??= 'manual';
        $data['current_value'] ??= 0;
        $milestone->update($data);

        return response()->json(['milestone' => $milestone->fresh()->setAttribute('progress_percent', $milestone->progressPercent())]);
    }

    public function deleteMilestone(Request $request, Milestone $milestone)
    {
        abort_unless($milestone->user_id === $request->user()->id, 403);
        $milestone->delete();

        return response()->noContent();
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
                'tasks' => $request->user()->tasks()->where('title', 'like', "%{$q}%")->take(8)->get(),
                'learning' => $request->user()->learningEntries()->where('topic', 'like', "%{$q}%")->take(8)->get(),
                'milestones' => $request->user()->milestones()->where('title', 'like', "%{$q}%")->take(8)->get(),
                'projects' => $request->user()->projects()->where('name', 'like', "%{$q}%")->take(8)->get(),
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

    private function applyTextSearch($query, Request $request, array $columns): void
    {
        $search = trim((string) $request->query('search'));
        if ($search === '') {
            return;
        }

        $query->where(function ($where) use ($columns, $search) {
            foreach ($columns as $column) {
                $where->orWhere($column, 'like', "%{$search}%");
            }
        });
    }

    private function paginateSorted($query, Request $request, string $defaultSort, int $perPage = 12, array $allowed = [])
    {
        $sort = in_array($request->query('sort'), $allowed, true) ? $request->query('sort') : $defaultSort;
        $direction = $request->query('direction') === 'asc' ? 'asc' : 'desc';

        return $query->orderBy($sort, $direction)
            ->paginate((int) min(max((int) $request->query('per_page', $perPage), 6), 50))
            ->withQueryString();
    }

    private function dailyProgressRules(): array
    {
        return [
            'date' => ['required', 'date'],
            'title' => ['required', 'string', 'max:180'],
            'in_progress' => ['nullable', 'string'],
            'todo' => ['nullable', 'string'],
            'blockers' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
            'mood' => ['nullable', 'string', 'max:80'],
            'completed_items' => ['nullable', 'array'],
            'tags' => ['nullable', 'array'],
            'archived' => ['boolean'],
        ];
    }

    private function workLogRules(): array
    {
        return [
            'date' => ['required', 'date'],
            'project_name' => ['required', 'string', 'max:120'],
            'ticket_code' => ['nullable', 'string', 'max:80'],
            'title' => ['required', 'string', 'max:180'],
            'category' => ['required', Rule::in(WorkLog::CATEGORIES)],
            'status' => ['required', Rule::in(WorkLog::STATUSES)],
            'priority' => ['required', Rule::in(WorkLog::PRIORITIES)],
            'description' => ['nullable', 'string'],
            'resolution_or_outcome' => ['nullable', 'string'],
            'estimated_duration' => ['nullable', 'integer', 'min:1'],
            'actual_duration' => ['nullable', 'integer', 'min:1'],
            'tags' => ['nullable', 'array'],
        ];
    }

    private function taskRules(Request $request): array
    {
        return [
            'title' => ['required', 'string', 'max:180'],
            'project_id' => ['nullable', 'integer', Rule::exists('projects', 'id')->where('user_id', $request->user()->id)],
            'notes' => ['nullable', 'string'],
            'status' => ['required', Rule::in(Task::STATUSES)],
            'priority' => ['required', Rule::in(Task::PRIORITIES)],
            'due_date' => ['nullable', 'date'],
        ];
    }

    private function learningRules(): array
    {
        return [
            'date' => ['required', 'date'],
            'topic' => ['required', 'string', 'max:180'],
            'category' => ['required', Rule::in(LearningEntry::CATEGORIES)],
            'source_type' => ['required', Rule::in(LearningEntry::SOURCE_TYPES)],
            'duration_minutes' => ['required', 'integer', 'min:1'],
            'progress_notes' => ['nullable', 'string'],
            'takeaway' => ['nullable', 'string'],
            'next_action' => ['nullable', 'string'],
            'rating' => ['nullable', 'integer', 'min:1', 'max:5'],
        ];
    }

    private function milestoneRules(): array
    {
        return [
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
        ];
    }
}
