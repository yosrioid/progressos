<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Goal;
use App\Models\KeyResult;
use App\Support\ApiResponse;
use Illuminate\Http\Request;

class GoalController extends Controller
{
    public function index(Request $request)
    {
        $goals = $request->user()->goals()
            ->with('keyResults')
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->status))
            ->orderByRaw("CASE status WHEN 'active' THEN 0 WHEN 'draft' THEN 1 WHEN 'completed' THEN 2 ELSE 3 END")
            ->orderByDesc('created_at')
            ->get()
            ->map(fn ($g) => $this->formatGoal($g));

        return response()->json(['goals' => $goals]);
    }

    public function show(Request $request, Goal $goal)
    {
        $this->authorize('view', $goal);
        $goal->load('keyResults');

        return response()->json(['goal' => $this->formatGoal($goal)]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'period_label' => ['nullable', 'string', 'max:50'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date'],
            'status' => ['nullable', 'in:draft,active,completed,abandoned'],
            'color' => ['nullable', 'string', 'max:20'],
            'key_results' => ['nullable', 'array'],
            'key_results.*.title' => ['required', 'string', 'max:255'],
            'key_results.*.metric_type' => ['nullable', 'in:percentage,number,boolean'],
            'key_results.*.target_value' => ['nullable', 'numeric'],
            'key_results.*.unit' => ['nullable', 'string', 'max:30'],
        ]);
        $data['user_id'] = $request->user()->id;
        $krs = $data['key_results'] ?? [];
        unset($data['key_results']);

        $goal = Goal::create($data);
        foreach ($krs as $i => $kr) {
            $goal->keyResults()->create([...$kr, 'user_id' => $request->user()->id, 'order' => $i]);
        }
        $goal->load('keyResults');

        return ApiResponse::item('goal', $this->formatGoal($goal), 201, 'Goal created.');
    }

    public function update(Request $request, Goal $goal)
    {
        $this->authorize('update', $goal);
        $data = $request->validate([
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'period_label' => ['nullable', 'string', 'max:50'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date'],
            'status' => ['nullable', 'in:draft,active,completed,abandoned'],
            'color' => ['nullable', 'string', 'max:20'],
        ]);
        $goal->update($data);

        return ApiResponse::item('goal', $this->formatGoal($goal->fresh()->load('keyResults')), 200, 'Goal updated.');
    }

    public function destroy(Goal $goal)
    {
        $this->authorize('delete', $goal);
        $goal->delete();

        return response()->noContent();
    }

    public function storeKeyResult(Request $request, Goal $goal)
    {
        $this->authorize('update', $goal);
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'metric_type' => ['nullable', 'in:percentage,number,boolean'],
            'current_value' => ['nullable', 'numeric'],
            'target_value' => ['nullable', 'numeric'],
            'unit' => ['nullable', 'string', 'max:30'],
            'notes' => ['nullable', 'string'],
        ]);
        $data['user_id'] = $request->user()->id;
        $data['order'] = $goal->keyResults()->count();
        $kr = $goal->keyResults()->create($data);

        return response()->json(['key_result' => $this->formatKr($kr)], 201);
    }

    public function updateKeyResult(Request $request, Goal $goal, KeyResult $keyResult)
    {
        $this->authorize('update', $goal);
        abort_if($keyResult->goal_id !== $goal->id, 403);
        $data = $request->validate([
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'metric_type' => ['nullable', 'in:percentage,number,boolean'],
            'current_value' => ['nullable', 'numeric'],
            'target_value' => ['nullable', 'numeric'],
            'unit' => ['nullable', 'string', 'max:30'],
            'notes' => ['nullable', 'string'],
            'status' => ['nullable', 'in:active,done,abandoned'],
        ]);
        $keyResult->update($data);

        // Auto-complete goal when all KRs done
        $goal->load('keyResults');
        if ($goal->keyResults->isNotEmpty() && $goal->keyResults->where('status', '!=', 'done')->where('status', '!=', 'abandoned')->isEmpty()) {
            $goal->update(['status' => 'completed']);
        }

        return response()->json(['key_result' => $this->formatKr($keyResult->fresh())]);
    }

    public function destroyKeyResult(Goal $goal, KeyResult $keyResult)
    {
        $this->authorize('update', $goal);
        abort_if($keyResult->goal_id !== $goal->id, 403);
        $keyResult->delete();

        return response()->noContent();
    }

    private function formatGoal(Goal $goal): array
    {
        $krs = $goal->keyResults ?? collect();

        return [
            'id' => $goal->id,
            'title' => $goal->title,
            'description' => $goal->description,
            'period_label' => $goal->period_label,
            'start_date' => $goal->start_date?->toDateString(),
            'end_date' => $goal->end_date?->toDateString(),
            'status' => $goal->status,
            'color' => $goal->color,
            'progress' => $goal->progressPercent(),
            'key_results' => $krs->map(fn ($kr) => $this->formatKr($kr))->values(),
            'created_at' => $goal->created_at?->toJSON(),
        ];
    }

    private function formatKr(KeyResult $kr): array
    {
        return [
            'id' => $kr->id,
            'title' => $kr->title,
            'metric_type' => $kr->metric_type,
            'current_value' => $kr->current_value,
            'target_value' => $kr->target_value,
            'unit' => $kr->unit,
            'notes' => $kr->notes,
            'status' => $kr->status,
            'order' => $kr->order,
            'progress' => $kr->progressPercent(),
        ];
    }
}
