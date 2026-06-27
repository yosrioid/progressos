<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TodoListItem extends Model
{
    protected $fillable = ['todo_list_id', 'content', 'completed', 'position', 'priority', 'due_date', 'notes'];

    protected function casts(): array
    {
        return [
            'completed' => 'boolean',
            'priority' => 'integer',
            'due_date' => 'date:Y-m-d',
        ];
    }

    public function list(): BelongsTo
    {
        return $this->belongsTo(TodoList::class, 'todo_list_id');
    }
}
