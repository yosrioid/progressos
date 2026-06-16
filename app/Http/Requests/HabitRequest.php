<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class HabitRequest extends FormRequest
{
    public function rules(): array
    {
        $isUpdate = (bool) $this->route('habit');

        return [
            'name' => [$isUpdate ? 'sometimes' : 'required', 'required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'color' => ['nullable', 'string', 'max:20'],
            'icon' => ['nullable', 'string', 'max:10'],
            'frequency' => ['nullable', 'in:daily,weekly'],
            'target_days' => ['nullable', 'array'],
            'target_days.*' => ['integer', 'min:0', 'max:6'],
            ...($isUpdate ? [
                'active' => ['nullable', 'boolean'],
                'archived' => ['nullable', 'boolean'],
                'order' => ['nullable', 'integer'],
            ] : []),
        ];
    }
}
