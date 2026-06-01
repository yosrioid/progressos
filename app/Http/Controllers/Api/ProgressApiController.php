<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\DailyProgressRequest;
use App\Http\Requests\LearningEntryRequest;
use App\Http\Requests\MilestoneRequest;
use App\Http\Requests\ProjectRequest;
use App\Http\Requests\QuickCaptureRequest;
use App\Http\Requests\ReferenceRequest;
use App\Http\Requests\SavedViewRequest;
use App\Http\Requests\TaskRequest;
use App\Http\Requests\WorkLogRequest;
use App\Models\AuditLog;
use App\Models\DailyProgressEntry;
use App\Models\LearningEntry;
use App\Models\Milestone;
use App\Models\Project;
use App\Models\Reference;
use App\Models\SavedView;
use App\Models\Task;
use App\Models\WorkLog;
use App\Services\DashboardData;
use App\Services\MilestoneProgressSync;
use App\Services\ProjectResolver;
use App\Services\ReportBuilder;
use App\Services\TagSyncer;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProgressApiController extends Controller
{
    private const REFERENCE_TYPES = [
        'task' => Task::class,
        'work_log' => WorkLog::class,
        'learning' => LearningEntry::class,
        'milestone' => Milestone::class,
        'daily_progress' => DailyProgressEntry::class,
    ];

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
        $this->authorize('view', $project);

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

    public function updateProject(ProjectRequest $request, Project $project)
    {
        $this->authorize('update', $project);
        $project->update($request->validated());

        return response()->json(['project' => $project->fresh()]);
    }

    public function quickCapture(QuickCaptureRequest $request, ProjectResolver $projects)
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
        $this->authorize('view', $dailyProgress);

        return response()->json(['entry' => $dailyProgress->load(['tags', 'references'])]);
    }

    public function storeDailyProgress(DailyProgressRequest $request, TagSyncer $tags)
    {
        $data = $request->validated();
        $tagNames = $data['tags'] ?? [];
        unset($data['tags']);
        $entry = $request->user()->dailyProgressEntries()->create($data);
        $tags->daily($entry, $request->user(), $tagNames);

        return response()->json(['entry' => $entry->load('tags')], 201);
    }

    public function updateDailyProgress(DailyProgressRequest $request, DailyProgressEntry $dailyProgress, TagSyncer $tags)
    {
        $this->authorize('update', $dailyProgress);
        $data = $request->validated();
        $tagNames = $data['tags'] ?? [];
        unset($data['tags']);
        $dailyProgress->update($data);
        $tags->daily($dailyProgress, $request->user(), $tagNames);

        return response()->json(['entry' => $dailyProgress->fresh()->load(['tags', 'references'])]);
    }

    public function deleteDailyProgress(Request $request, DailyProgressEntry $dailyProgress)
    {
        $this->authorize('delete', $dailyProgress);
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
        $this->authorize('view', $workLog);

        return response()->json(['log' => $workLog->load(['tags', 'references'])]);
    }

    public function storeWorkLog(WorkLogRequest $request, ProjectResolver $projects, TagSyncer $tags)
    {
        $data = $request->validated();
        $tagNames = $data['tags'] ?? [];
        unset($data['tags']);
        $data['project_id'] = $projects->resolve($request->user(), $data['project_name'])?->id;
        $log = $request->user()->workLogs()->create($data);
        $tags->workLog($log, $request->user(), $tagNames);

        return response()->json(['log' => $log->load('tags')], 201);
    }

    public function updateWorkLog(WorkLogRequest $request, WorkLog $workLog, ProjectResolver $projects, TagSyncer $tags)
    {
        $this->authorize('update', $workLog);
        $data = $request->validated();
        $tagNames = $data['tags'] ?? [];
        unset($data['tags']);
        $data['project_id'] = $projects->resolve($request->user(), $data['project_name'])?->id;
        $workLog->update($data);
        $tags->workLog($workLog, $request->user(), $tagNames);

        return response()->json(['log' => $workLog->fresh()->load(['tags', 'references'])]);
    }

    public function deleteWorkLog(Request $request, WorkLog $workLog)
    {
        $this->authorize('delete', $workLog);
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
        $this->authorize('view', $task);

        return response()->json(['task' => $task->load(['project', 'references'])]);
    }

    public function storeTask(TaskRequest $request)
    {
        $data = $request->validated();
        $data['completed_at'] = $data['status'] === 'done' ? now() : null;

        return response()->json(['task' => $request->user()->tasks()->create($data)], 201);
    }

    public function updateTask(TaskRequest $request, Task $task)
    {
        $this->authorize('update', $task);
        $data = $request->validated();
        $data['completed_at'] = $data['status'] === 'done' ? ($task->completed_at ?? now()) : null;
        $task->update($data);

        return response()->json(['task' => $task->fresh()->load(['project', 'references'])]);
    }

    public function updateTaskStatus(Request $request, Task $task)
    {
        $this->authorize('update', $task);
        $data = $request->validate(['status' => ['required', 'in:todo,in_progress,done,blocked']]);
        $task->update([...$data, 'completed_at' => $data['status'] === 'done' ? now() : null]);

        return response()->json(['task' => $task->fresh()]);
    }

    public function deleteTask(Request $request, Task $task)
    {
        $this->authorize('delete', $task);
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
        $this->authorize('view', $learning);

        return response()->json(['entry' => $learning->load('references')]);
    }

    public function storeLearning(LearningEntryRequest $request)
    {
        $data = $request->validated();

        return response()->json(['entry' => $request->user()->learningEntries()->create($data)], 201);
    }

    public function updateLearning(LearningEntryRequest $request, LearningEntry $learning)
    {
        $this->authorize('update', $learning);
        $learning->update($request->validated());

        return response()->json(['entry' => $learning->fresh()->load('references')]);
    }

    public function deleteLearning(Request $request, LearningEntry $learning)
    {
        $this->authorize('delete', $learning);
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
        $this->authorize('view', $milestone);

        return response()->json(['milestone' => $milestone->load('references')->setAttribute('progress_percent', $milestone->progressPercent())]);
    }

    public function storeMilestone(MilestoneRequest $request)
    {
        $data = $request->validated();
        $data['source_type'] ??= 'manual';
        $data['current_value'] ??= 0;

        return response()->json(['milestone' => $request->user()->milestones()->create($data)], 201);
    }

    public function updateMilestone(MilestoneRequest $request, Milestone $milestone)
    {
        $this->authorize('update', $milestone);
        $data = $request->validated();
        $data['source_type'] ??= 'manual';
        $data['current_value'] ??= 0;
        $milestone->update($data);

        return response()->json(['milestone' => $milestone->fresh()->load('references')->setAttribute('progress_percent', $milestone->progressPercent())]);
    }

    public function deleteMilestone(Request $request, Milestone $milestone)
    {
        $this->authorize('delete', $milestone);
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

    public function activity(Request $request)
    {
        $logs = $request->user()->auditLogs()
            ->latest()
            ->paginate((int) min(max((int) $request->query('per_page', 20), 10), 50));

        $logs->getCollection()->transform(fn (AuditLog $log) => [
            'id' => $log->id,
            'event' => $log->event,
            'label' => str($log->event)->replace('.', ' ')->headline()->toString(),
            'record_type' => class_basename((string) $log->auditable_type),
            'record_id' => $log->auditable_id,
            'metadata' => $log->metadata,
            'created_at' => $log->created_at,
        ]);

        return response()->json(['activity' => $logs]);
    }

    public function savedViews(Request $request)
    {
        $module = (string) $request->query('module');
        $query = $request->user()->savedViews()->latest('pinned')->latest();
        if ($module !== '') {
            $query->where('module', $module);
        }

        return response()->json(['saved_views' => $query->get()]);
    }

    public function storeSavedView(SavedViewRequest $request)
    {
        $data = $request->validated();

        $view = SavedView::updateOrCreate(
            ['user_id' => $request->user()->id, 'module' => $data['module'], 'name' => $data['name']],
            ['filters' => $data['filters'], 'pinned' => (bool) ($data['pinned'] ?? false)]
        );

        return response()->json(['saved_view' => $view], 201);
    }

    public function deleteSavedView(Request $request, SavedView $savedView)
    {
        $this->authorize('delete', $savedView);
        $savedView->delete();

        return response()->noContent();
    }

    public function storeReference(ReferenceRequest $request)
    {
        $data = $request->validated();

        $target = self::REFERENCE_TYPES[$data['referenceable_type']]::query()->findOrFail($data['referenceable_id']);
        $this->authorize('view', $target);

        $reference = $target->references()->create([
            'user_id' => $request->user()->id,
            'label' => $data['label'],
            'url' => $data['url'],
            'type' => $data['type'],
            'notes' => $data['notes'] ?? null,
        ]);

        return response()->json(['reference' => $reference], 201);
    }

    public function deleteReference(Request $request, Reference $reference)
    {
        $this->authorize('delete', $reference);
        $reference->delete();

        return response()->noContent();
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

}
