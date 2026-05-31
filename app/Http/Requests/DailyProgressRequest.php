<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class DailyProgressRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'date' => ['required', 'date'],
            'title' => ['required', 'string', 'max:160'],
            'in_progress' => ['nullable', 'string'],
            'todo' => ['nullable', 'string'],
            'blockers' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
            'completed_items' => ['nullable', 'array'],
            'completed_items.*' => ['nullable', 'string', 'max:255'],
            'mood' => ['nullable', 'string', 'max:40'],
            'archived' => ['boolean'],
            'tags' => ['nullable', 'array'],
            'tags.*' => ['string', 'max:40'],
        ];
    }
}
