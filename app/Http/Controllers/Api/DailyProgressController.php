<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\DailyProgressRequest;
use App\Http\Resources\DailyProgressResource;
use App\Models\DailyProgressEntry;
use App\Services\TagSyncer;
use App\Support\ApiQuery;
use Illuminate\Http\Request;

class DailyProgressController extends Controller
{
    public function index(Request $request)
    {
        $query = DailyProgressEntry::ownedBy($request->user())->with('tags');
        ApiQuery::applyTextSearch($query, $request, ['title', 'notes', 'in_progress', 'todo', 'blockers']);
        if ($request->filled('from')) {
            $query->whereDate('date', '>=', $request->query('from'));
        }
        if ($request->filled('to')) {
            $query->whereDate('date', '<=', $request->query('to'));
        }
        if ($request->filled('status')) {
            $query->where('mood', $request->query('status'));
        }
        if ($request->filled('tag')) {
            $query->whereHas('tags', fn ($tags) => $tags->where('name', $request->query('tag')));
        }
        if (! $request->boolean('archived')) {
            $query->where('archived', false);
        }

        return response()->json(['entries' => ApiQuery::paginateSorted($query, $request, 'date', 12, ['date', 'created_at', 'updated_at', 'title'])]);
    }

    public function show(Request $request, DailyProgressEntry $dailyProgress)
    {
        $this->authorize('view', $dailyProgress);

        return response()->json(['entry' => new DailyProgressResource($dailyProgress->load(['tags', 'references']))]);
    }

    public function store(DailyProgressRequest $request, TagSyncer $tags)
    {
        $data = $request->validated();
        $tagNames = $data['tags'] ?? [];
        unset($data['tags']);
        $entry = $request->user()->dailyProgressEntries()->create($data);
        $tags->daily($entry, $request->user(), $tagNames);

        return response()->json(['entry' => new DailyProgressResource($entry->load('tags'))], 201);
    }

    public function update(DailyProgressRequest $request, DailyProgressEntry $dailyProgress, TagSyncer $tags)
    {
        $this->authorize('update', $dailyProgress);
        $data = $request->validated();
        $tagNames = $data['tags'] ?? [];
        unset($data['tags']);
        $dailyProgress->update($data);
        $tags->daily($dailyProgress, $request->user(), $tagNames);

        return response()->json(['entry' => new DailyProgressResource($dailyProgress->fresh()->load(['tags', 'references']))]);
    }

    public function destroy(Request $request, DailyProgressEntry $dailyProgress)
    {
        $this->authorize('delete', $dailyProgress);
        $dailyProgress->delete();

        return response()->noContent();
    }
}
