<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\LearningEntryRequest;
use App\Http\Resources\LearningEntryResource;
use App\Models\LearningEntry;
use App\Models\Milestone;
use App\Services\MilestoneProgressSync;
use App\Support\ApiQuery;
use App\Support\ApiResponse;
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

        return ApiResponse::paginated('entries', ApiQuery::paginateSorted($query, $request, 'date', 12, ['date', 'created_at', 'updated_at', 'topic', 'category']), resourceClass: LearningEntryResource::class);
    }

    public function stats(Request $request)
    {
        $user = $request->user();
        $categories = $user->learningEntries()
            ->selectRaw('category, count(*) as entries, sum(duration_minutes) as total_minutes')
            ->groupBy('category')
            ->orderByDesc('total_minutes')
            ->get();

        $monthly = $user->learningEntries()
            ->selectRaw('DATE_FORMAT(date, "%Y-%m") as month, sum(duration_minutes) as total_minutes, count(*) as entries')
            ->whereDate('date', '>=', now()->subMonths(6)->startOfMonth())
            ->groupByRaw('DATE_FORMAT(date, "%Y-%m")')
            ->orderBy('month')
            ->get();

        return ApiResponse::ok([
            'categories' => $categories,
            'monthly' => $monthly,
            'totals' => [
                'entries' => $user->learningEntries()->count(),
                'minutes' => $user->learningEntries()->sum('duration_minutes'),
            ],
        ]);
    }

    public function heatmap(Request $request)
    {
        $user = $request->user();
        $from = now()->subDays(89)->toDateString();

        $rows = $user->learningEntries()
            ->selectRaw('DATE(date) as day, sum(duration_minutes) as minutes')
            ->whereDate('date', '>=', $from)
            ->groupByRaw('DATE(date)')
            ->pluck('minutes', 'day');

        $days = collect(range(0, 89))->map(fn ($i) => [
            'date' => now()->subDays(89 - $i)->toDateString(),
            'minutes' => (int) ($rows[now()->subDays(89 - $i)->toDateString()] ?? 0),
        ]);

        return ApiResponse::ok(['heatmap' => $days]);
    }

    public function show(Request $request, LearningEntry $learning)
    {
        $this->authorize('view', $learning);

        $milestones = $request->user()->milestones()
            ->where('status', 'active')
            ->where('source_type', 'learning_minutes')
            ->get()
            ->filter(function (Milestone $milestone) use ($learning) {
                $filter = trim((string) $milestone->source_filter);
                if (! $filter) {
                    return true;
                }

                return $learning->category === $filter
                    || str_contains(strtolower($learning->topic), strtolower($filter));
            })
            ->values()
            ->map(fn (Milestone $m) => [
                'id' => $m->id,
                'title' => $m->title,
                'current_value' => (float) $m->current_value,
                'target_value' => (float) $m->target_value,
                'progress_percent' => $m->progressPercent(),
            ]);

        return ApiResponse::item('entry', new LearningEntryResource($learning->load('references')), 200, null, [
            'contributing_milestones' => $milestones,
        ]);
    }

    public function store(LearningEntryRequest $request, MilestoneProgressSync $sync)
    {
        $entry = $request->user()->learningEntries()->create($request->validated());
        $sync->syncFor($request->user());

        return ApiResponse::item('entry', new LearningEntryResource($entry), 201, 'Learning entry created.');
    }

    public function update(LearningEntryRequest $request, LearningEntry $learning, MilestoneProgressSync $sync)
    {
        $this->authorize('update', $learning);
        $learning->update($request->validated());
        $sync->syncFor($request->user());

        return ApiResponse::item('entry', new LearningEntryResource($learning->fresh()->load('references')), 200, 'Learning entry updated.');
    }

    public function destroy(Request $request, LearningEntry $learning, MilestoneProgressSync $sync)
    {
        $this->authorize('delete', $learning);
        $learning->delete();
        $sync->syncFor($request->user());

        return response()->noContent();
    }
}
