<?php

namespace App\Http\Controllers;

use App\Http\Requests\LearningEntryRequest;
use App\Models\LearningEntry;
use Illuminate\Http\Request;
use Inertia\Inertia;

class LearningEntryController extends Controller
{
    public function index(Request $request)
    {
        $entries = LearningEntry::ownedBy($request->user())
            ->when($request->category, fn ($q, $v) => $q->where('category', $v))
            ->when($request->source_type, fn ($q, $v) => $q->where('source_type', $v))
            ->when($request->search, fn ($q, $s) => $q->where(fn ($w) => $w->where('topic', 'like', "%{$s}%")->orWhere('takeaway', 'like', "%{$s}%")))
            ->latest('date')->paginate(12)->withQueryString();

        $base = LearningEntry::ownedBy($request->user());

        return Inertia::render('Learning/Index', [
            'entries' => $entries,
            'filters' => $request->only('category', 'source_type', 'search'),
            'summary' => [
                'weekly_minutes' => (clone $base)->whereDate('date', '>=', now()->startOfWeek())->sum('duration_minutes'),
                'monthly_minutes' => (clone $base)->whereDate('date', '>=', now()->startOfMonth())->sum('duration_minutes'),
                'by_category' => (clone $base)->selectRaw('category, sum(duration_minutes) as minutes')->groupBy('category')->pluck('minutes', 'category'),
            ],
            'options' => ['categories' => LearningEntry::CATEGORIES, 'sourceTypes' => LearningEntry::SOURCE_TYPES],
        ]);
    }

    public function create()
    {
        return Inertia::render('Learning/Form', ['entry' => null, 'options' => ['categories' => LearningEntry::CATEGORIES, 'sourceTypes' => LearningEntry::SOURCE_TYPES]]);
    }

    public function store(LearningEntryRequest $request)
    {
        $entry = $request->user()->learningEntries()->create($request->validated());

        return redirect()->route('learning.show', $entry)->with('success', 'Learning entry saved.');
    }

    public function show(Request $request, LearningEntry $learning)
    {
        $this->guard($request, $learning);

        return Inertia::render('Learning/Show', ['entry' => $learning]);
    }

    public function edit(Request $request, LearningEntry $learning)
    {
        $this->guard($request, $learning);

        return Inertia::render('Learning/Form', ['entry' => $learning, 'options' => ['categories' => LearningEntry::CATEGORIES, 'sourceTypes' => LearningEntry::SOURCE_TYPES]]);
    }

    public function update(LearningEntryRequest $request, LearningEntry $learning)
    {
        $this->guard($request, $learning);
        $learning->update($request->validated());

        return redirect()->route('learning.show', $learning)->with('success', 'Learning entry updated.');
    }

    public function destroy(Request $request, LearningEntry $learning)
    {
        $this->guard($request, $learning);
        $learning->delete();

        return redirect()->route('learning.index')->with('success', 'Learning entry deleted.');
    }

    private function guard(Request $request, LearningEntry $entry): void
    {
        abort_unless($entry->user_id === $request->user()->id, 403);
    }
}
