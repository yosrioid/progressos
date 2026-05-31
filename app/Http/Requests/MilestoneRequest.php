<?php

namespace App\Http\Requests;

use App\Models\Milestone;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MilestoneRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:180'],
            'category' => ['required', 'string', 'max:80'],
            'target_type' => ['required', Rule::in(Milestone::TARGET_TYPES)],
            'source_type' => ['required', Rule::in(Milestone::SOURCE_TYPES)],
            'source_filter' => ['nullable', 'string', 'max:120'],
            'target_value' => ['required', 'numeric', 'min:1'],
            'current_value' => ['required', 'numeric', 'min:0'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'status' => ['required', Rule::in(Milestone::STATUSES)],
            'notes' => ['nullable', 'string'],
        ];
    }
}
