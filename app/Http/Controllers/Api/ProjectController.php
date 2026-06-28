<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ProjectRequest;
use App\Http\Resources\ProjectResource;
use App\Models\Project;
use App\Support\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProjectController extends Controller
{
    public function index(Request $request)
    {
        return ApiResponse::collection(
            'projects',
            ProjectResource::collection($request->user()->projects()
                ->withCount([
                    'tasks',
                    'tasks as open_tasks_count' => fn ($query) => $query->whereIn('status', ['todo', 'in_progress', 'blocked']),
                    'workLogs',
                ])
                ->where('archived', false)
                ->orderBy('name')
                ->get())
        );
    }

    public function show(Request $request, Project $project)
    {
        $this->authorize('view', $project);

        $monthExpr = DB::getDriverName() === 'sqlite'
            ? "strftime('%Y-%m', date)"
            : "DATE_FORMAT(date, '%Y-%m')";

        $monthly = $project->workLogs()
            ->selectRaw("{$monthExpr} as month, sum(actual_duration) as minutes, count(*) as logs")
            ->whereDate('date', '>=', now()->subMonths(6)->startOfMonth())
            ->groupByRaw($monthExpr)
            ->orderBy('month')
            ->get();

        $byCategory = $project->workLogs()
            ->selectRaw('category, sum(actual_duration) as minutes, count(*) as logs')
            ->groupBy('category')
            ->orderByDesc('minutes')
            ->get();

        $tasksByStatus = $project->tasks()
            ->selectRaw('status, count(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status');

        $taskStats = $project->tasks()
            ->selectRaw("COUNT(*) as total,
                SUM(CASE WHEN status IN ('todo','in_progress','blocked') THEN 1 ELSE 0 END) as open,
                SUM(CASE WHEN status = 'done' THEN 1 ELSE 0 END) as done,
                SUM(CASE WHEN status = 'blocked' THEN 1 ELSE 0 END) as blocked")
            ->first();

        $workStats = $project->workLogs()
            ->selectRaw("SUM(actual_duration) as minutes,
                SUM(CASE WHEN status = 'blocked' THEN 1 ELSE 0 END) as blocked")
            ->first();

        return ApiResponse::item('project', new ProjectResource($project), extra: [
            'tasks' => $project->tasks()->with('project')->orderBy('status')->orderBy('priority')->latest()->take(30)->get(),
            'workLogs' => $project->workLogs()->latest('date')->take(15)->get(),
            'metrics' => [
                'open_tasks' => (int) ($taskStats->open ?? 0),
                'completed_tasks' => (int) ($taskStats->done ?? 0),
                'logged_minutes' => (int) ($workStats->minutes ?? 0),
                'blockers' => (int) ($taskStats->blocked ?? 0) + (int) ($workStats->blocked ?? 0),
                'completion_rate' => ($taskStats->total ?? 0) > 0
                    ? round((int) ($taskStats->done ?? 0) / (int) $taskStats->total * 100)
                    : 0,
            ],
            'monthly_work' => $monthly,
            'by_category' => $byCategory,
            'tasks_by_status' => $tasksByStatus,
        ]);
    }

    public function update(ProjectRequest $request, Project $project)
    {
        $this->authorize('update', $project);
        $project->update($request->validated());

        return ApiResponse::item('project', new ProjectResource($project->fresh()), 200, 'Project updated.');
    }
}
