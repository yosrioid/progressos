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

        return ApiResponse::ok([
            'query' => $q,
            'results' => [
                'daily_progress' => $request->user()->dailyProgressEntries()->where('title', 'like', "%{$q}%")->take(8)->get(),
                'work_logs' => $request->user()->workLogs()->where('title', 'like', "%{$q}%")->take(8)->get(),
                'tasks' => $request->user()->tasks()->where('title', 'like', "%{$q}%")->take(8)->get(),
                'learning' => $request->user()->learningEntries()->where('topic', 'like', "%{$q}%")->take(8)->get(),
                'milestones' => $request->user()->milestones()->where('title', 'like', "%{$q}%")->take(8)->get(),
                'projects' => $request->user()->projects()->where('name', 'like', "%{$q}%")->take(8)->get(),
            ],
        ]);
    }
}
