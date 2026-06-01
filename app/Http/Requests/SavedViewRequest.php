<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SavedViewRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'module' => ['required', 'in:tasks,work-logs,daily-progress,learning,milestones'],
            'name' => ['required', 'string', 'max:80'],
            'filters' => ['required', 'array'],
            'pinned' => ['boolean'],
        ];
    }
}
