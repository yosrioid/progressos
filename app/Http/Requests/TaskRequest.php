<?php

namespace App\Http\Requests;

use App\Models\Task;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TaskRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:180'],
            'project_id' => ['nullable', 'integer', Rule::exists('projects', 'id')->where('user_id', $this->user()->id)],
            'milestone_id' => ['nullable', 'integer', Rule::exists('milestones', 'id')->where('user_id', $this->user()->id)],
            'work_log_id' => ['nullable', 'integer', Rule::exists('work_logs', 'id')->where('user_id', $this->user()->id)],
            'notes' => ['nullable', 'string'],
            'status' => ['required', Rule::in(Task::STATUSES)],
            'priority' => ['required', Rule::in(Task::PRIORITIES)],
            'due_date' => ['nullable', 'date'],
            'recurrence_rule' => ['nullable', 'in:daily,weekly,monthly,yearly'],
            'recurrence_interval' => ['nullable', 'integer', 'min:1', 'max:52'],
            'recurrence_days' => ['nullable', 'array'],
            'recurrence_days.*' => ['integer', 'min:0', 'max:6'],
            'recurrence_ends_at' => ['nullable', 'date'],
        ];
    }
}
