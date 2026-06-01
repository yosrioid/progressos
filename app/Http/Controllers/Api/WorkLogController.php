<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\WorkLogRequest;
use App\Http\Resources\WorkLogResource;
use App\Models\WorkLog;
use App\Services\ProjectResolver;
use App\Services\TagSyncer;
use App\Support\ApiQuery;
use Illuminate\Http\Request;

class WorkLogController extends Controller
{
    public function index(Request $request)
    {
        $query = WorkLog::ownedBy($request->user())->with('tags');
        ApiQuery::applyTextSearch($query, $request, ['title', 'project_name', 'ticket_code', 'description', 'resolution_or_outcome']);
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

        return response()->json(['logs' => ApiQuery::paginateSorted($query, $request, 'date', 12, ['date', 'created_at', 'updated_at', 'title', 'status', 'priority', 'category'])]);
    }

    public function show(Request $request, WorkLog $workLog)
    {
        $this->authorize('view', $workLog);

        return response()->json(['log' => new WorkLogResource($workLog->load(['tags', 'references']))]);
    }

    public function store(WorkLogRequest $request, ProjectResolver $projects, TagSyncer $tags)
    {
        $data = $request->validated();
        $tagNames = $data['tags'] ?? [];
        unset($data['tags']);
        $data['project_id'] = $projects->resolve($request->user(), $data['project_name'])?->id;
        $log = $request->user()->workLogs()->create($data);
        $tags->workLog($log, $request->user(), $tagNames);

        return response()->json(['log' => new WorkLogResource($log->load('tags'))], 201);
    }

    public function update(WorkLogRequest $request, WorkLog $workLog, ProjectResolver $projects, TagSyncer $tags)
    {
        $this->authorize('update', $workLog);
        $data = $request->validated();
        $tagNames = $data['tags'] ?? [];
        unset($data['tags']);
        $data['project_id'] = $projects->resolve($request->user(), $data['project_name'])?->id;
        $workLog->update($data);
        $tags->workLog($workLog, $request->user(), $tagNames);

        return response()->json(['log' => new WorkLogResource($workLog->fresh()->load(['tags', 'references']))]);
    }

    public function destroy(Request $request, WorkLog $workLog)
    {
        $this->authorize('delete', $workLog);
        $workLog->delete();

        return response()->noContent();
    }
}
