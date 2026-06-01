<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\MilestoneRequest;
use App\Http\Resources\MilestoneResource;
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
}
