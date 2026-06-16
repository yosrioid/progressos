<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class GoalRequest extends FormRequest
{
    public function rules(): array
    {
        $isUpdate = (bool) $this->route('goal');

        return [
            'title' => [$isUpdate ? 'sometimes' : 'required', 'required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'period_label' => ['nullable', 'string', 'max:50'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date'],
            'status' => ['nullable', 'in:draft,active,completed,abandoned'],
            'color' => ['nullable', 'string', 'max:20'],
            ...(! $isUpdate ? [
                'key_results' => ['nullable', 'array'],
                'key_results.*.title' => ['required', 'string', 'max:255'],
                'key_results.*.metric_type' => ['nullable', 'in:percentage,number,boolean'],
                'key_results.*.target_value' => ['nullable', 'numeric'],
                'key_results.*.unit' => ['nullable', 'string', 'max:30'],
            ] : []),
        ];
    }
}
