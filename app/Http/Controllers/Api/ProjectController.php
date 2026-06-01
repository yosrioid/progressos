<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ProjectRequest;
use App\Http\Resources\ProjectResource;
use App\Models\Project;
use App\Support\ApiResponse;
use Illuminate\Http\Request;

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

        return ApiResponse::item('project', new ProjectResource($project), extra: [
            'tasks' => $project->tasks()->with('project')->latest()->take(20)->get(),
            'workLogs' => $project->workLogs()->with('tags')->latest('date')->take(20)->get(),
            'metrics' => [
                'open_tasks' => $project->tasks()->whereIn('status', ['todo', 'in_progress', 'blocked'])->count(),
                'completed_tasks' => $project->tasks()->where('status', 'done')->count(),
                'logged_minutes' => $project->workLogs()->sum('actual_duration'),
                'blockers' => $project->tasks()->where('status', 'blocked')->count() + $project->workLogs()->where('status', 'blocked')->count(),
            ],
        ]);
    }

    public function update(ProjectRequest $request, Project $project)
    {
        $this->authorize('update', $project);
        $project->update($request->validated());

        return ApiResponse::item('project', new ProjectResource($project->fresh()), 200, 'Project updated.');
    }
}
