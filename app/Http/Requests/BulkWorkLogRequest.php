<?php

namespace App\Http\Requests;

use App\Models\WorkLog;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BulkWorkLogRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'logs' => ['required', 'array', 'min:1', 'max:50'],
            'logs.*.date' => ['required', 'date'],
            'logs.*.project_name' => ['required', 'string', 'max:120'],
            'logs.*.ticket_code' => ['nullable', 'string', 'max:80'],
            'logs.*.title' => ['required', 'string', 'max:180'],
            'logs.*.category' => ['required', Rule::in(WorkLog::CATEGORIES)],
            'logs.*.status' => ['required', Rule::in(WorkLog::STATUSES)],
            'logs.*.priority' => ['required', Rule::in(WorkLog::PRIORITIES)],
            'logs.*.description' => ['nullable', 'string'],
            'logs.*.resolution_or_outcome' => ['nullable', 'string'],
            'logs.*.estimated_duration' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'logs.*.actual_duration' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'logs.*.task_id' => ['nullable', 'integer', 'exists:tasks,id'],
            'logs.*.tags' => ['nullable', 'array'],
            'logs.*.tags.*' => ['string', 'max:40'],
        ];
    }
}
