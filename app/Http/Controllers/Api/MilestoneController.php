<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\MilestoneRequest;
use App\Http\Resources\DailyProgressResource;
use App\Http\Resources\LearningEntryResource;
use App\Http\Resources\MilestoneResource;
use App\Http\Resources\TaskResource;
use App\Http\Resources\WorkLogResource;
use App\Models\Milestone;
use App\Support\ApiQuery;
use App\Support\ApiResponse;
use Illuminate\Http\Request;

class MilestoneController extends Controller
{
    public function index(Request $request)
    {
        $query = Milestone::ownedBy($request->user());
        ApiQuery::applyTextSearch($query, $request, ['title', 'category', 'notes']);
        foreach (['status', 'target_type', 'category'] as $filter) {
            if ($request->filled($filter)) {
                $query->where($filter, $request->query($filter));
            }
        }

        return ApiResponse::paginated('milestones', ApiQuery::paginateSorted($query, $request, 'created_at', 20, ['created_at', 'updated_at', 'end_date', 'title', 'status', 'category']), resourceClass: MilestoneResource::class);
    }

    public function show(Request $request, Milestone $milestone)
    {
        $this->authorize('view', $milestone);

        return ApiResponse::item('milestone', new MilestoneResource($milestone->load('references')));
    }

    public function store(MilestoneRequest $request)
    {
        $data = $request->validated();
        $data['source_type'] ??= 'manual';
        $data['current_value'] ??= 0;

        return ApiResponse::item('milestone', new MilestoneResource($request->user()->milestones()->create($data)), 201, 'Milestone created.');
    }

    public function update(MilestoneRequest $request, Milestone $milestone)
    {
        $this->authorize('update', $milestone);
        $data = $request->validated();
        $data['source_type'] ??= 'manual';
        $data['current_value'] ??= 0;
        $milestone->update($data);

        return ApiResponse::item('milestone', new MilestoneResource($milestone->fresh()->load('references')), 200, 'Milestone updated.');
    }

    public function destroy(Request $request, Milestone $milestone)
    {
        $this->authorize('delete', $milestone);
        $milestone->delete();

        return response()->noContent();
    }

    public function history(Request $request, Milestone $milestone)
    {
        $this->authorize('view', $milestone);

        $filter = trim((string) $milestone->source_filter);
        $user = $request->user();

        switch ($milestone->source_type) {
            case 'learning_minutes':
                $query = $user->learningEntries()
                    ->when($filter, fn ($q) => $q->where(fn ($i) => $i->where('category', $filter)->orWhere('topic', 'like', "%{$filter}%")));

                return ApiResponse::paginated('items', ApiQuery::paginateSorted($query, $request, 'date', 20, ['date', 'topic', 'category']), resourceClass: LearningEntryResource::class);

            case 'work_log_count':
            case 'work_log_minutes':
                $query = $user->workLogs()
                    ->when($filter, fn ($q) => $q->where(fn ($i) => $i->where('project_name', $filter)->orWhere('category', $filter)));

                return ApiResponse::paginated('items', ApiQuery::paginateSorted($query, $request, 'date', 20, ['date', 'title', 'project_name', 'created_at']), resourceClass: WorkLogResource::class);

            case 'task_done_count':
                $query = $user->tasks()
                    ->where('status', 'done')
                    ->when($filter, fn ($q) => $q->where('priority', $filter));

                return ApiResponse::paginated('items', ApiQuery::paginateSorted($query, $request, 'created_at', 20, ['created_at', 'updated_at', 'due_date', 'title']), resourceClass: TaskResource::class);

            case 'daily_progress_streak':
                $query = $user->dailyProgressEntries();

                return ApiResponse::paginated('items', ApiQuery::paginateSorted($query, $request, 'date', 20, ['date', 'title', 'created_at']), resourceClass: DailyProgressResource::class);

            default:
                return response()->json(['items' => null, 'meta' => null]);
        }
    }
}
