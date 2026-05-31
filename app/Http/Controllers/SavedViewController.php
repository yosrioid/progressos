<?php

namespace App\Http\Controllers;

use App\Models\SavedView;
use Illuminate\Http\Request;

class SavedViewController extends Controller
{
    public function store(Request $request)
    {
        $data = $request->validate([
            'module' => ['required', 'in:tasks,work_logs,daily_progress,learning,milestones'],
            'name' => ['required', 'string', 'max:80'],
            'filters' => ['required', 'array'],
            'pinned' => ['boolean'],
        ]);

        SavedView::updateOrCreate(
            ['user_id' => $request->user()->id, 'module' => $data['module'], 'name' => $data['name']],
            ['filters' => $data['filters'], 'pinned' => $request->boolean('pinned')]
        );

        return back()->with('success', 'View saved.');
    }

    public function destroy(Request $request, SavedView $savedView)
    {
        $this->authorize('delete', $savedView);
        $savedView->delete();

        return back()->with('success', 'View removed.');
    }
}
