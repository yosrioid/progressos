<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Support\ApiResponse;
use Illuminate\Http\Request;

class SearchController extends Controller
{
    public function __invoke(Request $request)
    {
        $q = trim((string) $request->query('q'));
        if ($q === '') {
            return ApiResponse::ok(['query' => '', 'results' => []]);
        }

        $user = $request->user();
        $like = "%{$q}%";

        return ApiResponse::ok([
            'query' => $q,
            'results' => [
                'daily_progress' => $user->dailyProgressEntries()->where('title', 'like', $like)->take(8)->get(),
                'work_logs' => $user->workLogs()->where('title', 'like', $like)->take(8)->get(),
                'tasks' => $user->tasks()->where('title', 'like', $like)->take(8)->get(),
                'learning' => $user->learningEntries()->where('topic', 'like', $like)->take(8)->get(),
                'milestones' => $user->milestones()->where('title', 'like', $like)->take(8)->get(),
                'projects' => $user->projects()->where('name', 'like', $like)->take(8)->get(),
                'goals' => $user->goals()->where('title', 'like', $like)->take(8)->get(),
                'habits' => $user->habits()->where('name', 'like', $like)->take(8)->get(['id', 'name', 'icon', 'color', 'frequency']),
                'docs' => $user->docs()->where('title', 'like', $like)->take(8)->get(['id', 'title', 'category', 'updated_at']),
            ],
        ]);
    }
}
