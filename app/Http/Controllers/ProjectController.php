<?php

namespace App\Http\Controllers;

use App\Models\Task;
use App\Models\Project;
use App\Models\WorkLog;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class ProjectController extends Controller
{
    public function index(Request $request)
    {
        return Inertia::render('Projects/Index', [
            'projects' => $request->user()->projects()
                ->withCount([
                    'tasks',
                    'tasks as open_tasks_count' => fn ($q) => $q->whereIn('status', ['todo', 'in_progress', 'blocked']),
                    'workLogs',
                ])
                ->where('archived', false)
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function show(Request $request, Project $project)
    {
        $this->authorize('view', $project);
        $today = now($request->user()->timezone)->toDateString();
        $weekStart = now($request->user()->timezone)->startOfWeek()->toDateString();
        $weekEnd = now($request->user()->timezone)->endOfWeek()->toDateString();
        $thirtyDaysAgo = now($request->user()->timezone)->subDays(29)->toDateString();

        return Inertia::render('Projects/Show', [
            'project' => $project,
            'tasks' => $project->tasks()
                ->with('milestone')
                ->whereIn('status', ['todo', 'in_progress', 'blocked'])
                ->orderByRaw("case when status = 'blocked' then 0 when status = 'in_progress' then 1 else 2 end")
                ->orderByRaw('case when due_date is null then 1 else 0 end')
                ->orderBy('due_date')
                ->latest()
                ->take(12)
                ->get(),
            'blockers' => [
                ...$project->tasks()->where('status', 'blocked')->latest()->take(8)->get()->map(fn (Task $task) => [
                    'id' => $task->id,
                    'type' => 'task',
                    'title' => $task->title,
                    'date' => $task->due_date,
                    'href' => route('tasks.show', $task),
                ]),
                ...$project->workLogs()->where('status', 'blocked')->latest('date')->take(8)->get()->map(fn (WorkLog $log) => [
                    'id' => $log->id,
                    'type' => 'work_log',
                    'title' => $log->title,
                    'date' => $log->date,
                    'href' => route('work-logs.show', $log),
                ]),
            ],
            'todayLogs' => $project->workLogs()->with('tags')->whereDate('date', $today)->latest()->take(8)->get(),
            'workLogs' => $project->workLogs()->with('tags')->latest('date')->take(12)->get(),
            'weeklyTrend' => $project->workLogs()
                ->whereBetween('date', [$thirtyDaysAgo, $today])
                ->selectRaw('date, coalesce(sum(actual_duration), 0) as minutes, count(*) as total')
                ->groupBy('date')
                ->orderBy('date')
                ->get()
                ->map(fn ($row) => ['date' => Carbon::parse($row->date)->toDateString(), 'minutes' => (int) $row->minutes, 'total' => (int) $row->total]),
            'categoryBreakdown' => $project->workLogs()
                ->whereBetween('date', [$weekStart, $weekEnd])
                ->selectRaw('category, coalesce(sum(actual_duration), 0) as minutes, count(*) as total')
                ->groupBy('category')
                ->orderByDesc('minutes')
                ->get(),
            'metrics' => [
                'open_tasks' => $project->tasks()->whereIn('status', ['todo', 'in_progress', 'blocked'])->count(),
                'completed_tasks' => $project->tasks()->where('status', 'done')->count(),
                'logged_minutes' => $project->workLogs()->sum('actual_duration'),
                'week_minutes' => $project->workLogs()->whereBetween('date', [$weekStart, $weekEnd])->sum('actual_duration'),
                'today_logs' => $project->workLogs()->whereDate('date', $today)->count(),
                'blockers' => $project->tasks()->where('status', 'blocked')->count() + $project->workLogs()->where('status', 'blocked')->count(),
            ],
            'options' => [
                'taskStatuses' => Task::STATUSES,
                'taskPriorities' => Task::PRIORITIES,
                'workCategories' => WorkLog::CATEGORIES,
            ],
        ]);
    }

    public function update(Request $request, Project $project)
    {
        $this->authorize('update', $project);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120', Rule::unique('projects')->where('user_id', $request->user()->id)->ignore($project->id)],
            'archived' => ['required', 'boolean'],
        ]);

        $oldName = $project->name;
        $project->update($data);

        if ($oldName !== $data['name']) {
            $project->workLogs()->where('project_name', $oldName)->update(['project_name' => $data['name']]);
        }

        return $data['archived']
            ? redirect()->route('projects.index')->with('success', 'Project archived.')
            : redirect()->route('projects.show', $project)->with('success', 'Project updated.');
    }

    public function storeTask(Request $request, Project $project)
    {
        $this->authorize('update', $project);
        $data = $request->validate([
            'title' => ['required', 'string', 'max:180'],
            'notes' => ['nullable', 'string'],
            'status' => ['required', Rule::in(Task::STATUSES)],
            'priority' => ['required', Rule::in(Task::PRIORITIES)],
            'due_date' => ['nullable', 'date'],
        ]);

        if ($data['status'] === 'done') {
            $data['completed_at'] = now();
        }

        $request->user()->tasks()->create([...$data, 'project_id' => $project->id]);

        return back()->with('success', 'Project task added.');
    }

    public function storeWorkLog(Request $request, Project $project)
    {
        $this->authorize('update', $project);
        $data = $request->validate([
            'date' => ['required', 'date'],
            'title' => ['required', 'string', 'max:180'],
            'category' => ['required', Rule::in(WorkLog::CATEGORIES)],
            'actual_duration' => ['nullable', 'integer', 'min:1', 'max:10000'],
            'description' => ['nullable', 'string'],
        ]);

        $request->user()->workLogs()->create([
            ...$data,
            'project_id' => $project->id,
            'project_name' => $project->name,
            'status' => 'done',
            'priority' => 'medium',
        ]);

        return back()->with('success', 'Project work logged.');
    }
}
