<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ProjectRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'color' => ['nullable', 'string', 'max:32'],
            'archived' => ['boolean'],
        ];
    }
}
