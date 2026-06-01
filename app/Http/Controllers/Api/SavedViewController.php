<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\SavedViewRequest;
use App\Models\SavedView;
use Illuminate\Http\Request;

class SavedViewController extends Controller
{
    public function index(Request $request)
    {
        $module = (string) $request->query('module');
        $query = $request->user()->savedViews()->latest('pinned')->latest();
        if ($module !== '') {
            $query->where('module', $module);
        }

        return response()->json(['saved_views' => $query->get()]);
    }

    public function store(SavedViewRequest $request)
    {
        $data = $request->validated();
        $view = SavedView::updateOrCreate(
            ['user_id' => $request->user()->id, 'module' => $data['module'], 'name' => $data['name']],
            ['filters' => $data['filters'], 'pinned' => (bool) ($data['pinned'] ?? false)]
        );

        return response()->json(['saved_view' => $view], 201);
    }

    public function destroy(Request $request, SavedView $savedView)
    {
        $this->authorize('delete', $savedView);
        $savedView->delete();

        return response()->noContent();
    }
}
