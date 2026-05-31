<?php

namespace App\Http\Controllers;

use App\Http\Requests\MilestoneRequest;
use App\Models\Milestone;
use Illuminate\Http\Request;
use Inertia\Inertia;

class MilestoneController extends Controller
{
    public function index(Request $request)
    {
        $milestones = Milestone::ownedBy($request->user())
            ->when($request->status, fn ($q, $v) => $q->where('status', $v))
            ->when($request->category, fn ($q, $v) => $q->where('category', $v))
            ->when($request->search, fn ($q, $s) => $q->where('title', 'like', "%{$s}%"))
            ->orderByRaw("case when status = 'active' then 0 when status = 'paused' then 1 else 2 end")
            ->orderBy('end_date')->paginate(12)->withQueryString();

        $milestones->getCollection()->transform(fn ($m) => $m->setAttribute('progress_percent', $m->progressPercent())->setAttribute('overdue', $m->end_date?->isPast() && $m->status !== 'completed'));

        return Inertia::render('Milestones/Index', [
            'milestones' => $milestones,
            'filters' => $request->only('status', 'category', 'search'),
            'options' => ['statuses' => Milestone::STATUSES, 'targetTypes' => Milestone::TARGET_TYPES, 'sourceTypes' => Milestone::SOURCE_TYPES],
        ]);
    }

    public function create()
    {
        return Inertia::render('Milestones/Form', ['milestone' => null, 'options' => ['statuses' => Milestone::STATUSES, 'targetTypes' => Milestone::TARGET_TYPES, 'sourceTypes' => Milestone::SOURCE_TYPES]]);
    }

    public function store(MilestoneRequest $request)
    {
        $milestone = $request->user()->milestones()->create($request->validated());

        return redirect()->route('milestones.show', $milestone)->with('success', 'Milestone saved.');
    }

    public function show(Request $request, Milestone $milestone)
    {
        $this->guard($request, $milestone);

        return Inertia::render('Milestones/Show', ['milestone' => $milestone->setAttribute('progress_percent', $milestone->progressPercent())]);
    }

    public function edit(Request $request, Milestone $milestone)
    {
        $this->guard($request, $milestone);

        return Inertia::render('Milestones/Form', ['milestone' => $milestone, 'options' => ['statuses' => Milestone::STATUSES, 'targetTypes' => Milestone::TARGET_TYPES, 'sourceTypes' => Milestone::SOURCE_TYPES]]);
    }

    public function update(MilestoneRequest $request, Milestone $milestone)
    {
        $this->guard($request, $milestone);
        $milestone->update($request->validated());

        return redirect()->route('milestones.show', $milestone)->with('success', 'Milestone updated.');
    }

    public function destroy(Request $request, Milestone $milestone)
    {
        $this->guard($request, $milestone);
        $milestone->delete();

        return redirect()->route('milestones.index')->with('success', 'Milestone deleted.');
    }

    private function guard(Request $request, Milestone $milestone): void
    {
        abort_unless($milestone->user_id === $request->user()->id, 403);
    }
}
