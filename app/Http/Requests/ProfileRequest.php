<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProfileRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users')->ignore($this->user()->id)],
            'timezone' => ['required', 'timezone'],
            'theme' => ['required', Rule::in(['system', 'light', 'dark'])],
            'avatar' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'dimensions:min_width=32,min_height=32,max_width=4000,max_height=4000', 'max:2048'],
        ];
    }
}
