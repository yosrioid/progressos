<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\TaskRequest;
use App\Http\Resources\TaskResource;
use App\Models\Task;
use App\Services\MilestoneProgressSync;
use App\Support\ApiQuery;
use App\Support\ApiResponse;
use Illuminate\Http\Request;

class TaskController extends Controller
{
    public function index(Request $request)
    {
        $query = Task::ownedBy($request->user())->with('project');
        ApiQuery::applyTextSearch($query, $request, ['title', 'notes']);
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

        return ApiResponse::paginated('tasks', ApiQuery::paginateSorted($query, $request, 'created_at', 20, ['created_at', 'updated_at', 'due_date', 'title', 'status', 'priority']), resourceClass: TaskResource::class);
    }

    public function kanban(Request $request)
    {
        $statuses = ['todo', 'in_progress', 'blocked', 'done'];
        $board = [];
        foreach ($statuses as $status) {
            $board[$status] = Task::ownedBy($request->user())
                ->with('project')
                ->where('status', $status)
                ->orderBy('priority')
                ->when($status === 'done', fn ($q) => $q->orderByDesc('completed_at')->limit(20))
                ->when($status !== 'done', fn ($q) => $q->orderBy('due_date')->limit(50))
                ->get(['id', 'title', 'priority', 'status', 'due_date', 'project_id', 'completed_at'])
                ->map(fn (Task $t) => [
                    'id' => $t->id,
                    'title' => $t->title,
                    'priority' => $t->priority,
                    'status' => $t->status,
                    'due_date' => $t->due_date?->toDateString(),
                    'project_name' => $t->project?->name,
                    'overdue' => $t->status !== 'done' && $t->due_date && $t->due_date->isPast(),
                ]);
        }

        return response()->json(['board' => $board]);
    }

    public function overdueCount(Request $request)
    {
        $count = $request->user()->tasks()
            ->whereNotIn('status', ['done', 'cancelled'])
            ->whereNotNull('due_date')
            ->whereDate('due_date', '<', today())
            ->count();

        return response()->json(['count' => $count]);
    }

    public function show(Request $request, Task $task)
    {
        $this->authorize('view', $task);

        return ApiResponse::item('task', new TaskResource($task->load(['project', 'references'])));
    }

    public function store(TaskRequest $request, MilestoneProgressSync $sync)
    {
        $data = $request->validated();
        $data['completed_at'] = $data['status'] === 'done' ? now() : null;
        $task = $request->user()->tasks()->create($data);
        $sync->syncFor($request->user());

        return ApiResponse::item('task', new TaskResource($task), 201, 'Task created.');
    }

    public function update(TaskRequest $request, Task $task, MilestoneProgressSync $sync)
    {
        $this->authorize('update', $task);
        $data = $request->validated();
        $wasDone = $task->status === 'done';
        $data['completed_at'] = $data['status'] === 'done' ? ($task->completed_at ?? now()) : null;
        $task->update($data);
        $sync->syncFor($request->user());

        if (! $wasDone && $data['status'] === 'done') {
            $this->spawnRecurrence($task);
        }

        return ApiResponse::item('task', new TaskResource($task->fresh()->load(['project', 'references'])), 200, 'Task updated.');
    }

    public function updateStatus(Request $request, Task $task, MilestoneProgressSync $sync)
    {
        $this->authorize('update', $task);
        $data = $request->validate(['status' => ['required', 'in:todo,in_progress,done,blocked']]);
        $wasDone = $task->status === 'done';
        $task->update([...$data, 'completed_at' => $data['status'] === 'done' ? now() : null]);
        $sync->syncFor($request->user());

        if (! $wasDone && $data['status'] === 'done') {
            $this->spawnRecurrence($task);
        }

        return ApiResponse::item('task', new TaskResource($task->fresh()), 200, 'Task status updated.');
    }

    private function spawnRecurrence(Task $task): void
    {
        if (! $task->recurrence_rule) {
            return;
        }
        $nextDue = $task->nextDueDate();
        if (! $nextDue) {
            return;
        }
        if ($task->recurrence_ends_at && $nextDue->isAfter($task->recurrence_ends_at)) {
            return;
        }
        Task::create([
            'user_id' => $task->user_id,
            'project_id' => $task->project_id,
            'milestone_id' => $task->milestone_id,
            'title' => $task->title,
            'notes' => $task->notes,
            'status' => 'todo',
            'priority' => $task->priority,
            'due_date' => $nextDue,
            'recurrence_rule' => $task->recurrence_rule,
            'recurrence_interval' => $task->recurrence_interval,
            'recurrence_days' => $task->recurrence_days,
            'recurrence_ends_at' => $task->recurrence_ends_at,
            'parent_task_id' => $task->id,
        ]);
    }

    public function destroy(Request $request, Task $task, MilestoneProgressSync $sync)
    {
        $this->authorize('delete', $task);
        $task->delete();
        $sync->syncFor($request->user());

        return response()->noContent();
    }
}
