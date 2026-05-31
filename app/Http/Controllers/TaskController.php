<?php

namespace App\Http\Controllers;

use App\Http\Requests\TaskRequest;
use App\Models\Task;
use Illuminate\Http\Request;
use Inertia\Inertia;

class TaskController extends Controller
{
    public function index(Request $request)
    {
        $tasks = Task::ownedBy($request->user())->with(['project', 'milestone'])
            ->when($request->status, fn ($q, $v) => $q->where('status', $v))
            ->when($request->priority, fn ($q, $v) => $q->where('priority', $v))
            ->when($request->search, fn ($q, $s) => $q->where('title', 'like', "%{$s}%"))
            ->orderByRaw("case when status = 'done' then 1 else 0 end")
            ->orderByRaw('case when due_date is null then 1 else 0 end')
            ->orderBy('due_date')
            ->latest()
            ->paginate(14)->withQueryString();

        return Inertia::render('Tasks/Index', [
            'tasks' => $tasks,
            'filters' => $request->only('status', 'priority', 'search'),
            'savedViews' => $request->user()->savedViews()->where('module', 'tasks')->orderByDesc('pinned')->orderBy('name')->get(),
            'options' => $this->options($request),
            'summary' => Task::ownedBy($request->user())->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status'),
        ]);
    }

    public function create(Request $request)
    {
        return Inertia::render('Tasks/Form', ['task' => null, 'options' => $this->options($request)]);
    }

    public function store(TaskRequest $request)
    {
        $data = $this->withCompletedAt($request->validated());
        $task = $request->user()->tasks()->create($data);

        return redirect()->route('tasks.show', $task)->with('success', 'Task saved.');
    }

    public function show(Request $request, Task $task)
    {
        $this->authorize('view', $task);

        return Inertia::render('Tasks/Show', ['task' => $task->load(['project', 'milestone', 'workLog', 'references'])]);
    }

    public function edit(Request $request, Task $task)
    {
        $this->authorize('update', $task);

        return Inertia::render('Tasks/Form', ['task' => $task->load(['project', 'milestone']), 'options' => $this->options($request)]);
    }

    public function update(TaskRequest $request, Task $task)
    {
        $this->authorize('update', $task);
        $task->update($this->withCompletedAt($request->validated(), $task));

        return redirect()->route('tasks.show', $task)->with('success', 'Task updated.');
    }

    public function destroy(Request $request, Task $task)
    {
        $this->authorize('delete', $task);
        $task->delete();

        return redirect()->route('tasks.index')->with('success', 'Task deleted.');
    }

    public function status(Request $request, Task $task)
    {
        $this->authorize('update', $task);
        $data = $request->validate(['status' => ['required', 'in:todo,in_progress,done,blocked']]);
        $task->update($this->withCompletedAt($data, $task));

        return back()->with('success', 'Task status updated.');
    }

    private function options(Request $request): array
    {
        return [
            'statuses' => Task::STATUSES,
            'priorities' => Task::PRIORITIES,
            'projects' => $request->user()->projects()->orderBy('name')->get(['id', 'name']),
            'milestones' => $request->user()->milestones()->whereIn('status', ['active', 'paused'])->orderBy('title')->get(['id', 'title']),
        ];
    }

    private function withCompletedAt(array $data, ?Task $task = null): array
    {
        if (($data['status'] ?? null) === 'done' && ! $task?->completed_at) {
            $data['completed_at'] = now();
        }

        if (($data['status'] ?? null) !== 'done') {
            $data['completed_at'] = null;
        }

        return $data;
    }

}
