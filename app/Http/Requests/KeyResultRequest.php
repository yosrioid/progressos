<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class KeyResultRequest extends FormRequest
{
    public function rules(): array
    {
        $isUpdate = (bool) $this->route('keyResult');

        return [
            'title'         => [$isUpdate ? 'sometimes' : 'required', 'required', 'string', 'max:255'],
            'metric_type'   => ['nullable', 'in:percentage,number,boolean'],
            'current_value' => ['nullable', 'numeric'],
            'target_value'  => ['nullable', 'numeric'],
            'unit'          => ['nullable', 'string', 'max:30'],
            'notes'         => ['nullable', 'string'],
            ...($isUpdate ? [
                'status' => ['nullable', 'in:active,done,abandoned'],
            ] : []),
        ];
    }
}
