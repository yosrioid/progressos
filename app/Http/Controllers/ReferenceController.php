<?php

namespace App\Http\Controllers;

use App\Models\DailyProgressEntry;
use App\Models\LearningEntry;
use App\Models\Milestone;
use App\Models\Reference;
use App\Models\Task;
use App\Models\WorkLog;
use App\Rules\SafeHttpUrl;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class ReferenceController extends Controller
{
    private const TYPES = [
        'task' => Task::class,
        'work_log' => WorkLog::class,
        'learning' => LearningEntry::class,
        'milestone' => Milestone::class,
        'daily_progress' => DailyProgressEntry::class,
    ];

    public function store(Request $request)
    {
        $data = $request->validate([
            'referenceable_type' => ['required', 'in:task,work_log,learning,milestone,daily_progress'],
            'referenceable_id' => ['required', 'integer'],
            'label' => ['required', 'string', 'max:160'],
            'url' => ['required', 'max:2000', new SafeHttpUrl],
            'type' => ['required', 'in:link,doc,ticket,pr,article,course,other'],
            'notes' => ['nullable', 'string'],
        ]);

        $target = $this->target($request, $data['referenceable_type'], (int) $data['referenceable_id']);

        $target->references()->create([
            'user_id' => $request->user()->id,
            'label' => $data['label'],
            'url' => $data['url'],
            'type' => $data['type'],
            'notes' => $data['notes'] ?? null,
        ]);

        return back()->with('success', 'Reference added.');
    }

    public function destroy(Request $request, Reference $reference)
    {
        $this->authorize('delete', $reference);
        $reference->delete();

        return back()->with('success', 'Reference removed.');
    }

    private function target(Request $request, string $type, int $id): Model
    {
        $class = self::TYPES[$type];
        $target = $class::query()->findOrFail($id);
        abort_unless($request->user()->can('view', $target), 403);

        return $target;
    }
}
