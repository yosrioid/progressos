<?php

namespace App\Http\Controllers;

use App\Http\Requests\WorkLogRequest;
use App\Models\WorkLog;
use App\Services\ProjectResolver;
use App\Services\TagSyncer;
use Illuminate\Http\Request;
use Inertia\Inertia;

class WorkLogController extends Controller
{
    public function index(Request $request)
    {
        $logs = WorkLog::ownedBy($request->user())->with('tags')
            ->when($request->project, fn ($q, $v) => $q->where('project_name', $v))
            ->when($request->status, fn ($q, $v) => $q->where('status', $v))
            ->when($request->category, fn ($q, $v) => $q->where('category', $v))
            ->when($request->search, fn ($q, $s) => $q->where(fn ($w) => $w->where('title', 'like', "%{$s}%")->orWhere('ticket_code', 'like', "%{$s}%")->orWhere('description', 'like', "%{$s}%")))
            ->latest('date')->paginate(12)->withQueryString();

        return Inertia::render('WorkLogs/Index', [
            'logs' => $logs,
            'filters' => $request->only('project', 'status', 'category', 'search'),
            'summary' => WorkLog::ownedBy($request->user())->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status'),
            'options' => ['categories' => WorkLog::CATEGORIES, 'statuses' => WorkLog::STATUSES, 'priorities' => WorkLog::PRIORITIES],
        ]);
    }

    public function create()
    {
        return Inertia::render('WorkLogs/Form', ['log' => null, 'options' => ['categories' => WorkLog::CATEGORIES, 'statuses' => WorkLog::STATUSES, 'priorities' => WorkLog::PRIORITIES]]);
    }

    public function store(WorkLogRequest $request, TagSyncer $tags, ProjectResolver $projects)
    {
        $data = $request->safe()->except('tags');
        $data['project_id'] = $projects->resolve($request->user(), $data['project_name'])?->id;
        $log = $request->user()->workLogs()->create($data);
        $tags->workLog($log, $request->user(), $request->validated('tags', []));

        return redirect()->route('work-logs.show', $log)->with('success', 'Work log saved.');
    }

    public function show(Request $request, WorkLog $workLog)
    {
        $this->guard($request, $workLog);

        return Inertia::render('WorkLogs/Show', ['log' => $workLog->load('tags')]);
    }

    public function edit(Request $request, WorkLog $workLog)
    {
        $this->guard($request, $workLog);

        return Inertia::render('WorkLogs/Form', ['log' => $workLog->load('tags'), 'options' => ['categories' => WorkLog::CATEGORIES, 'statuses' => WorkLog::STATUSES, 'priorities' => WorkLog::PRIORITIES]]);
    }

    public function update(WorkLogRequest $request, WorkLog $workLog, TagSyncer $tags, ProjectResolver $projects)
    {
        $this->guard($request, $workLog);
        $data = $request->safe()->except('tags');
        $data['project_id'] = $projects->resolve($request->user(), $data['project_name'])?->id;
        $workLog->update($data);
        $tags->workLog($workLog, $request->user(), $request->validated('tags', []));

        return redirect()->route('work-logs.show', $workLog)->with('success', 'Work log updated.');
    }

    public function destroy(Request $request, WorkLog $workLog)
    {
        $this->guard($request, $workLog);
        $workLog->delete();

        return redirect()->route('work-logs.index')->with('success', 'Work log deleted.');
    }

    public function bulkStatus(Request $request)
    {
        $data = $request->validate(['ids' => ['required', 'array'], 'ids.*' => ['integer'], 'status' => ['required', 'in:todo,in_progress,done,blocked']]);
        WorkLog::ownedBy($request->user())->whereIn('id', $data['ids'])->update(['status' => $data['status']]);

        return back()->with('success', 'Statuses updated.');
    }

    private function guard(Request $request, WorkLog $log): void
    {
        abort_unless($log->user_id === $request->user()->id, 403);
    }
}
