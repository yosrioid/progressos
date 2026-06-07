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

        return ApiResponse::item('project', new ProjectResource($project), extra: [
            'tasks' => $project->tasks()->with('project')->orderBy('status')->orderBy('priority')->latest()->take(30)->get(),
            'workLogs' => $project->workLogs()->latest('date')->take(15)->get(),
            'metrics' => [
                'open_tasks' => $project->tasks()->whereIn('status', ['todo', 'in_progress', 'blocked'])->count(),
                'completed_tasks' => $project->tasks()->where('status', 'done')->count(),
                'logged_minutes' => $project->workLogs()->sum('actual_duration'),
                'blockers' => $project->tasks()->where('status', 'blocked')->count() + $project->workLogs()->where('status', 'blocked')->count(),
                'completion_rate' => $project->tasks()->count() > 0
                    ? round($project->tasks()->where('status', 'done')->count() / $project->tasks()->count() * 100)
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
