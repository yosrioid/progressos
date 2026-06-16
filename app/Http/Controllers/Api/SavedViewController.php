<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\SavedViewRequest;
use App\Models\SavedView;
use App\Support\ApiResponse;
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

        return ApiResponse::collection('saved_views', $query->get());
    }

    public function store(SavedViewRequest $request)
    {
        $data = $request->validated();
        $view = SavedView::updateOrCreate(
            ['user_id' => $request->user()->id, 'module' => $data['module'], 'name' => $data['name']],
            ['filters' => $data['filters'], 'pinned' => (bool) ($data['pinned'] ?? false)]
        );

        return ApiResponse::item('saved_view', $view, 201, 'Saved view stored.');
    }

    public function setDefault(Request $request, SavedView $savedView)
    {
        $this->authorize('update', $savedView);

        $isDefault = ! $savedView->is_default;

        // Unset all other defaults in the same module first
        $request->user()->savedViews()
            ->where('module', $savedView->module)
            ->where('id', '!=', $savedView->id)
            ->update(['is_default' => false]);

        $savedView->update(['is_default' => $isDefault]);

        return ApiResponse::item('saved_view', $savedView->fresh());
    }

    public function destroy(Request $request, SavedView $savedView)
    {
        $this->authorize('delete', $savedView);
        $savedView->delete();

        return response()->noContent();
    }
}
