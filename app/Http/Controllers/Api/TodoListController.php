<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\TodoList;
use App\Models\TodoListItem;
use App\Support\ApiResponse;
use Illuminate\Http\Request;

class TodoListController extends Controller
{
    public function index(Request $request)
    {
        $lists = TodoList::ownedBy($request->user())
            ->withCount([
                'items',
                'items as completed_count' => fn ($q) => $q->where('completed', true),
                'items as due_today_count' => fn ($q) => $q->where('completed', false)->whereDate('due_date', today()),
            ])
            ->latest()
            ->get();

        return ApiResponse::collection('lists', $lists);
    }

    public function store(Request $request)
    {
        $data = $request->validate(['title' => ['required', 'string', 'max:200']]);
        $list = $request->user()->todoLists()->create($data);

        return ApiResponse::item('list', $list->loadCount('items'), 201, 'List created.');
    }

    public function show(Request $request, TodoList $todoList)
    {
        $this->authorize('view', $todoList);

        return ApiResponse::item('list', $todoList->load('items'));
    }

    public function update(Request $request, TodoList $todoList)
    {
        $this->authorize('update', $todoList);
        $data = $request->validate(['title' => ['required', 'string', 'max:200']]);
        $todoList->update($data);

        return ApiResponse::item('list', $todoList->fresh());
    }

    public function destroy(Request $request, TodoList $todoList)
    {
        $this->authorize('delete', $todoList);
        $todoList->delete();

        return response()->noContent();
    }

    public function storeItem(Request $request, TodoList $todoList)
    {
        $this->authorize('update', $todoList);
        $data = $request->validate(['content' => ['required', 'string', 'max:1000']]);

        $position = $todoList->items()->max('position') + 1;
        $item = $todoList->items()->create([
            'content' => $data['content'],
            'completed' => false,
            'position' => $position,
        ]);

        return ApiResponse::item('item', $item, 201);
    }

    public function updateItem(Request $request, TodoList $todoList, TodoListItem $item)
    {
        $this->authorize('update', $todoList);
        if ($item->todo_list_id !== $todoList->id) {
            abort(404);
        }

        $data = $request->validate([
            'content' => ['sometimes', 'string', 'max:1000'],
            'completed' => ['sometimes', 'boolean'],
            'position' => ['sometimes', 'integer', 'min:0'],
            'priority' => ['sometimes', 'integer', 'in:0,1,2,3'],
            'due_date' => ['sometimes', 'nullable', 'date'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:5000'],
        ]);
        $item->update($data);

        return ApiResponse::item('item', $item->fresh());
    }

    public function destroyItem(Request $request, TodoList $todoList, TodoListItem $item)
    {
        $this->authorize('update', $todoList);
        if ($item->todo_list_id !== $todoList->id) {
            abort(404);
        }
        $item->delete();

        return response()->noContent();
    }
}
