<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProjectRequest extends FormRequest
{
    public function rules(): array
    {
        $projectId = $this->route('project')?->getKey();
        $userId = $this->user()?->id;

        return [
            'name' => [
                'required',
                'string',
                'max:120',
                Rule::unique('projects', 'name')
                    ->where('user_id', $userId)
                    ->ignore($projectId),
            ],
            'color' => ['nullable', 'string', 'max:32'],
            'archived' => ['boolean'],
        ];
    }
}
