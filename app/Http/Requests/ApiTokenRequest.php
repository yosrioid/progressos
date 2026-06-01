<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ApiTokenRequest extends FormRequest
{
    public const ABILITIES = ['*', 'read', 'write', 'capture', 'reports', 'tokens'];

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'abilities' => ['nullable', 'array'],
            'abilities.*' => ['string', Rule::in(self::ABILITIES)],
            'expires_at' => ['nullable', 'date', 'after:now'],
        ];
    }
}
