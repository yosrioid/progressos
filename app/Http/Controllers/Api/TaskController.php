<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\TaskRequest;
use App\Http\Resources\TaskResource;
use App\Models\Task;
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

    public function show(Request $request, Task $task)
    {
        $this->authorize('view', $task);

        return ApiResponse::item('task', new TaskResource($task->load(['project', 'references'])));
    }

    public function store(TaskRequest $request)
    {
        $data = $request->validated();
        $data['completed_at'] = $data['status'] === 'done' ? now() : null;

        return ApiResponse::item('task', new TaskResource($request->user()->tasks()->create($data)), 201, 'Task created.');
    }

    public function update(TaskRequest $request, Task $task)
    {
        $this->authorize('update', $task);
        $data = $request->validated();
        $data['completed_at'] = $data['status'] === 'done' ? ($task->completed_at ?? now()) : null;
        $task->update($data);

        return ApiResponse::item('task', new TaskResource($task->fresh()->load(['project', 'references'])), 200, 'Task updated.');
    }

    public function updateStatus(Request $request, Task $task)
    {
        $this->authorize('update', $task);
        $data = $request->validate(['status' => ['required', 'in:todo,in_progress,done,blocked']]);
        $task->update([...$data, 'completed_at' => $data['status'] === 'done' ? now() : null]);

        return ApiResponse::item('task', new TaskResource($task->fresh()), 200, 'Task status updated.');
    }

    public function destroy(Request $request, Task $task)
    {
        $this->authorize('delete', $task);
        $task->delete();

        return response()->noContent();
    }
}
