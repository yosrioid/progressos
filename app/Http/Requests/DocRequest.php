<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class DocRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'category' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string'],
            'reference_urls' => ['nullable', 'array'],
            'reference_urls.*.title' => ['nullable', 'string', 'max:255'],
            'reference_urls.*.url' => ['nullable', 'string', 'url', 'max:2000'],
        ];
    }
}
