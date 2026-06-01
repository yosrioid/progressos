<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class QuickCaptureRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'type' => ['required', 'in:task,work_log,daily_progress,learning,blocker'],
            'title' => ['required', 'string', 'max:180'],
            'date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
            'project_name' => ['nullable', 'string', 'max:120'],
            'duration_minutes' => ['nullable', 'integer', 'min:1', 'max:10000'],
        ];
    }
}
