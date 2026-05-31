<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;

class SearchController extends Controller
{
    public function __invoke(Request $request)
    {
        $q = trim((string) $request->query('q'));
        $user = $request->user();

        $results = $q === '' ? [] : [
            'tasks' => $user->tasks()->where('title', 'like', "%{$q}%")->latest()->take(8)->get(['id', 'title', 'status', 'due_date']),
            'daily_progress' => $user->dailyProgressEntries()->where('title', 'like', "%{$q}%")->latest('date')->take(8)->get(['id', 'date', 'title']),
            'work_logs' => $user->workLogs()->where(fn ($w) => $w->where('title', 'like', "%{$q}%")->orWhere('project_name', 'like', "%{$q}%")->orWhere('ticket_code', 'like', "%{$q}%"))->latest('date')->take(8)->get(['id', 'date', 'title', 'project_name', 'status']),
            'learning' => $user->learningEntries()->where('topic', 'like', "%{$q}%")->latest('date')->take(8)->get(['id', 'date', 'topic', 'category']),
            'milestones' => $user->milestones()->where('title', 'like', "%{$q}%")->latest()->take(8)->get(['id', 'title', 'category', 'status']),
        ];

        return Inertia::render('Search', ['query' => $q, 'results' => $results]);
    }
}
