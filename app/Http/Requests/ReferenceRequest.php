<?php

namespace App\Http\Requests;

use App\Rules\SafeHttpUrl;
use Illuminate\Foundation\Http\FormRequest;

class ReferenceRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'referenceable_type' => ['required', 'in:task,work_log,learning,milestone,daily_progress'],
            'referenceable_id' => ['required', 'integer'],
            'label' => ['required', 'string', 'max:160'],
            'url' => ['required', 'max:2000', new SafeHttpUrl],
            'type' => ['required', 'in:link,doc,ticket,pr,article,course,other'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
