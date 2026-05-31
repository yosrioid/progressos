<?php

namespace App\Http\Controllers;

use App\Models\ReviewEntry;
use App\Models\Task;
use App\Services\ReportBuilder;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ReviewController extends Controller
{
    public function daily(Request $request)
    {
        $date = CarbonImmutable::parse($request->query('date', now($request->user()->timezone)->toDateString()));
        $review = $this->review($request, 'daily', $date, $date);

        return Inertia::render('Reviews/Daily', [
            'date' => $date->toDateString(),
            'tomorrow' => $date->addDay()->toDateString(),
            'review' => $review,
            'progress' => $request->user()->dailyProgressEntries()->with('tags')->whereDate('date', $date)->latest()->get(),
            'tasks' => $request->user()->tasks()->with('project')->where(fn ($q) => $q->whereDate('due_date', $date)->orWhere(fn ($w) => $w->whereNull('due_date')->whereIn('status', ['todo', 'in_progress', 'blocked'])))->orderByRaw("case when status = 'blocked' then 0 when status = 'in_progress' then 1 when status = 'todo' then 2 else 3 end")->get(),
            'completedTasks' => $request->user()->tasks()->with('project')->where('status', 'done')->whereDate('completed_at', $date)->latest('completed_at')->get(),
            'blockers' => [
                ...$request->user()->tasks()->with('project')->where('status', 'blocked')->get()->map(fn (Task $task) => [
                    'id' => $task->id,
                    'type' => 'task',
                    'title' => $task->title,
                    'project' => $task->project?->name,
                    'date' => $task->due_date,
                    'href' => route('tasks.show', $task),
                ]),
                ...$request->user()->workLogs()->where('status', 'blocked')->latest('date')->get()->map(fn ($log) => [
                    'id' => $log->id,
                    'type' => 'work_log',
                    'title' => $log->title,
                    'project' => $log->project_name,
                    'date' => $log->date,
                    'href' => route('work-logs.show', $log),
                ]),
            ],
            'workLogs' => $request->user()->workLogs()->whereDate('date', $date)->latest()->get(),
            'learning' => $request->user()->learningEntries()->whereDate('date', $date)->latest()->get(),
            'projects' => $request->user()->projects()->where('archived', false)->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function period(Request $request, ReportBuilder $builder, string $period)
    {
        abort_unless(in_array($period, ['weekly', 'monthly'], true), 404);

        $report = $builder->build($request->user(), $period, $request->query('date'));
        $review = $this->review($request, $period, CarbonImmutable::parse($report['start']), CarbonImmutable::parse($report['end']));

        return Inertia::render('Reviews/Period', ['report' => $report, 'review' => $review]);
    }

    public function save(Request $request)
    {
        $data = $request->validate([
            'period_type' => ['required', 'in:daily,weekly,monthly'],
            'period_start' => ['required', 'date'],
            'period_end' => ['required', 'date'],
            'answers' => ['nullable', 'array'],
            'summary' => ['nullable', 'string'],
        ]);

        ReviewEntry::updateOrCreate(
            ['user_id' => $request->user()->id, 'period_type' => $data['period_type'], 'period_start' => $data['period_start']],
            ['period_end' => $data['period_end'], 'answers' => $data['answers'] ?? [], 'summary' => $data['summary'] ?? null]
        );

        return back()->with('success', 'Review saved.');
    }

    public function planTask(Request $request)
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:180'],
            'project_id' => ['nullable', 'integer', 'exists:projects,id'],
            'priority' => ['required', 'in:low,medium,high,urgent'],
            'due_date' => ['required', 'date'],
            'notes' => ['nullable', 'string'],
        ]);

        if (! empty($data['project_id'])) {
            abort_unless($request->user()->projects()->whereKey($data['project_id'])->exists(), 403);
        }

        $request->user()->tasks()->create([
            ...$data,
            'status' => 'todo',
        ]);

        return back()->with('success', 'Tomorrow plan added.');
    }

    public function carryTask(Request $request, Task $task)
    {
        abort_unless($task->user_id === $request->user()->id, 403);
        $data = $request->validate(['due_date' => ['required', 'date']]);

        $task->update(['due_date' => $data['due_date'], 'status' => $task->status === 'done' ? 'todo' : $task->status]);

        return back()->with('success', 'Task carried forward.');
    }

    public function taskToWorkLog(Request $request, Task $task)
    {
        abort_unless($task->user_id === $request->user()->id, 403);

        $log = $request->user()->workLogs()->create([
            'project_id' => $task->project_id,
            'project_name' => $task->project?->name ?: 'General',
            'date' => now($request->user()->timezone)->toDateString(),
            'title' => $task->title,
            'category' => 'other',
            'status' => 'done',
            'priority' => $task->priority,
            'description' => $task->notes,
        ]);
        $task->update(['work_log_id' => $log->id, 'status' => 'done', 'completed_at' => now()]);

        return back()->with('success', 'Task converted to work log.');
    }

    private function review(Request $request, string $period, CarbonImmutable $start, CarbonImmutable $end): ReviewEntry
    {
        return ReviewEntry::firstOrCreate(
            ['user_id' => $request->user()->id, 'period_type' => $period, 'period_start' => $start->toDateString()],
            ['period_end' => $end->toDateString(), 'answers' => []]
        );
    }
}
