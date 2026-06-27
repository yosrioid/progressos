<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\WorkLogRequest;
use App\Http\Resources\WorkLogResource;
use App\Models\WorkLog;
use App\Services\MilestoneProgressSync;
use App\Services\ProjectResolver;
use App\Services\TagSyncer;
use App\Support\ApiQuery;
use App\Support\ApiResponse;
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

        return ApiResponse::paginated('logs', ApiQuery::paginateSorted($query, $request, 'date', 12, ['date', 'created_at', 'updated_at', 'title', 'status', 'priority', 'category']), resourceClass: WorkLogResource::class);
    }

    public function show(Request $request, WorkLog $workLog)
    {
        $this->authorize('view', $workLog);

        return ApiResponse::item('log', new WorkLogResource($workLog->load(['tags', 'references', 'task'])));
    }

    public function store(WorkLogRequest $request, ProjectResolver $projects, TagSyncer $tags, MilestoneProgressSync $sync)
    {
        $data = $request->validated();
        $projectNames = collect($data['project_names'] ?? [])
            ->map(fn ($name) => trim((string) $name))
            ->filter()
            ->unique()
            ->values();

        if ($projectNames->isEmpty()) {
            $projectNames = collect([$data['project_name']]);
        }

        unset($data['project_names']);

        $logs = $projectNames->map(function (string $projectName) use ($request, $data, $projects, $tags) {
            $row = $data;
            $row['project_name'] = $projectName;

            return $this->createLog($request, $row, $projects, $tags)->load('tags');
        });

        $sync->syncFor($request->user());
        $message = $logs->count() > 1 ? "{$logs->count()} work logs created." : 'Work log created.';

        return ApiResponse::item('log', new WorkLogResource($logs->first()), 201, $message, [
            'logs' => WorkLogResource::collection($logs),
            'created_count' => $logs->count(),
        ]);
    }

    public function update(WorkLogRequest $request, WorkLog $workLog, ProjectResolver $projects, TagSyncer $tags, MilestoneProgressSync $sync)
    {
        $this->authorize('update', $workLog);
        $data = $request->validated();
        $tagNames = $data['tags'] ?? [];
        unset($data['tags']);
        $data['project_id'] = $projects->resolve($request->user(), $data['project_name'])?->id;
        $workLog->update($data);
        $tags->workLog($workLog, $request->user(), $tagNames);
        $sync->syncFor($request->user());

        return ApiResponse::item('log', new WorkLogResource($workLog->fresh()->load(['tags', 'references'])), 200, 'Work log updated.');
    }

    public function destroy(Request $request, WorkLog $workLog, MilestoneProgressSync $sync)
    {
        $this->authorize('delete', $workLog);
        $workLog->delete();
        $sync->syncFor($request->user());

        return response()->noContent();
    }

    private function createLog(Request $request, array $data, ProjectResolver $projects, TagSyncer $tags): WorkLog
    {
        $tagNames = $data['tags'] ?? [];
        unset($data['tags']);
        $data['project_id'] = $projects->resolve($request->user(), $data['project_name'])?->id;
        $log = $request->user()->workLogs()->create($data);
        $tags->workLog($log, $request->user(), $tagNames);

        return $log;
    }
}
