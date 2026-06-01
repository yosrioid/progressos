<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\LearningEntryRequest;
use App\Http\Resources\LearningEntryResource;
use App\Models\LearningEntry;
use App\Support\ApiQuery;
use Illuminate\Http\Request;

class LearningController extends Controller
{
    public function index(Request $request)
    {
        $query = LearningEntry::ownedBy($request->user());
        ApiQuery::applyTextSearch($query, $request, ['topic', 'progress_notes', 'takeaway', 'next_action']);
        foreach (['category', 'source_type'] as $filter) {
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

        return response()->json(['entries' => ApiQuery::paginateSorted($query, $request, 'date', 12, ['date', 'created_at', 'updated_at', 'topic', 'category'])]);
    }

    public function show(Request $request, LearningEntry $learning)
    {
        $this->authorize('view', $learning);

        return response()->json(['entry' => new LearningEntryResource($learning->load('references'))]);
    }

    public function store(LearningEntryRequest $request)
    {
        return response()->json(['entry' => new LearningEntryResource($request->user()->learningEntries()->create($request->validated()))], 201);
    }

    public function update(LearningEntryRequest $request, LearningEntry $learning)
    {
        $this->authorize('update', $learning);
        $learning->update($request->validated());

        return response()->json(['entry' => new LearningEntryResource($learning->fresh()->load('references'))]);
    }

    public function destroy(Request $request, LearningEntry $learning)
    {
        $this->authorize('delete', $learning);
        $learning->delete();

        return response()->noContent();
    }
}
