<?php

namespace App\Http\Controllers;

use App\Http\Requests\DailyProgressRequest;
use App\Models\DailyProgressEntry;
use App\Services\TagSyncer;
use Illuminate\Http\Request;
use Inertia\Inertia;

class DailyProgressController extends Controller
{
    public function index(Request $request)
    {
        $entries = DailyProgressEntry::ownedBy($request->user())->with('tags')
            ->when(! $request->boolean('archived'), fn ($q) => $q->where('archived', false))
            ->when($request->from, fn ($q, $from) => $q->whereDate('date', '>=', $from))
            ->when($request->to, fn ($q, $to) => $q->whereDate('date', '<=', $to))
            ->when($request->tag, fn ($q, $tag) => $q->whereHas('tags', fn ($t) => $t->where('name', $tag)))
            ->when($request->search, fn ($q, $s) => $q->where(fn ($w) => $w->where('title', 'like', "%{$s}%")->orWhere('notes', 'like', "%{$s}%")->orWhere('blockers', 'like', "%{$s}%")))
            ->latest('date')->paginate(12)->withQueryString();

        return Inertia::render('DailyProgress/Index', ['entries' => $entries, 'filters' => $request->only('from', 'to', 'tag', 'search', 'archived')]);
    }

    public function create()
    {
        return Inertia::render('DailyProgress/Form', ['entry' => null]);
    }

    public function store(DailyProgressRequest $request, TagSyncer $tags)
    {
        $entry = $request->user()->dailyProgressEntries()->create($request->safe()->except('tags'));
        $tags->daily($entry, $request->user(), $request->validated('tags', []));

        return redirect()->route('daily-progress.show', $entry)->with('success', 'Daily progress saved.');
    }

    public function show(Request $request, DailyProgressEntry $dailyProgress)
    {
        $this->guard($request, $dailyProgress);

        return Inertia::render('DailyProgress/Show', ['entry' => $dailyProgress->load('tags')]);
    }

    public function edit(Request $request, DailyProgressEntry $dailyProgress)
    {
        $this->guard($request, $dailyProgress);

        return Inertia::render('DailyProgress/Form', ['entry' => $dailyProgress->load('tags')]);
    }

    public function update(DailyProgressRequest $request, DailyProgressEntry $dailyProgress, TagSyncer $tags)
    {
        $this->guard($request, $dailyProgress);
        $dailyProgress->update($request->safe()->except('tags'));
        $tags->daily($dailyProgress, $request->user(), $request->validated('tags', []));

        return redirect()->route('daily-progress.show', $dailyProgress)->with('success', 'Daily progress updated.');
    }

    public function destroy(Request $request, DailyProgressEntry $dailyProgress)
    {
        $this->guard($request, $dailyProgress);
        $dailyProgress->delete();

        return redirect()->route('daily-progress.index')->with('success', 'Daily progress deleted.');
    }

    public function duplicate(Request $request)
    {
        $previous = DailyProgressEntry::ownedBy($request->user())->whereDate('date', '<', now()->toDateString())->latest('date')->with('tags')->firstOrFail();
        $copy = $previous->replicate(['date', 'archived']);
        $date = now($request->user()->timezone);
        $copy->date = $date->toDateString();
        $copy->title = 'Plan for '.$date->format('M j, Y');
        $copy->archived = false;
        $copy->save();
        $copy->tags()->sync($previous->tags->pluck('id'));

        return redirect()->route('daily-progress.edit', $copy);
    }

    public function archive(Request $request, DailyProgressEntry $dailyProgress)
    {
        $this->guard($request, $dailyProgress);
        $dailyProgress->update(['archived' => true]);

        return back()->with('success', 'Entry archived.');
    }

    private function guard(Request $request, DailyProgressEntry $entry): void
    {
        abort_unless($entry->user_id === $request->user()->id, 403);
    }
}
