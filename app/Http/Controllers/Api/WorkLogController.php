<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\BulkWorkLogRequest;
use App\Http\Requests\WorkLogRequest;
use App\Http\Resources\WorkLogResource;
use App\Models\WorkLog;
use App\Services\MilestoneProgressSync;
use App\Services\ProjectResolver;
use App\Services\TagSyncer;
use App\Support\ApiQuery;
use App\Support\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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
        $log = $this->createLog($request, $data, $projects, $tags);
        $sync->syncFor($request->user());

        return ApiResponse::item('log', new WorkLogResource($log->load('tags')), 201, 'Work log created.');
    }

    public function bulkStore(BulkWorkLogRequest $request, ProjectResolver $projects, TagSyncer $tags, MilestoneProgressSync $sync)
    {
        $logs = DB::transaction(function () use ($request, $projects, $tags) {
            return collect($request->validated('logs'))
                ->map(fn (array $data) => $this->createLog($request, $data, $projects, $tags)->load('tags'))
                ->values();
        });

        $sync->syncFor($request->user());

        return ApiResponse::collection('logs', WorkLogResource::collection($logs), 201, 'Work logs created.', [
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
