<?php

namespace App\Http\Requests;

use App\Models\WorkLog;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class WorkLogRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'date' => ['required', 'date'],
            'project_name' => ['required', 'string', 'max:120'],
            'ticket_code' => ['nullable', 'string', 'max:80'],
            'title' => ['required', 'string', 'max:180'],
            'category' => ['required', Rule::in(WorkLog::CATEGORIES)],
            'status' => ['required', Rule::in(WorkLog::STATUSES)],
            'priority' => ['required', Rule::in(WorkLog::PRIORITIES)],
            'description' => ['nullable', 'string'],
            'resolution_or_outcome' => ['nullable', 'string'],
            'estimated_duration' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'actual_duration' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'task_id' => ['nullable', 'integer', 'exists:tasks,id'],
            'tags' => ['nullable', 'array'],
            'tags.*' => ['string', 'max:40'],
        ];
    }
}
