<?php

namespace App\Http\Requests;

use App\Models\LearningEntry;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class LearningEntryRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'date' => ['required', 'date'],
            'topic' => ['required', 'string', 'max:180'],
            'category' => ['required', Rule::in(LearningEntry::CATEGORIES)],
            'source_type' => ['required', Rule::in(LearningEntry::SOURCE_TYPES)],
            'duration_minutes' => ['required', 'integer', 'min:1', 'max:10000'],
            'progress_notes' => ['nullable', 'string'],
            'takeaway' => ['nullable', 'string'],
            'next_action' => ['nullable', 'string'],
            'rating' => ['nullable', 'integer', 'min:1', 'max:5'],
        ];
    }
}
