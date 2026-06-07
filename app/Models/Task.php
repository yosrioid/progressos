<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Project;
use Carbon\Carbon;
use Database\Factories\TaskFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property Carbon|null $due_date
 * @property Carbon|null $completed_at
 * @property Carbon|null $recurrence_ends_at
 * @property-read Project|null $project
 */
class Task extends Model
{
    /** @use HasFactory<TaskFactory> */
    use Auditable, HasFactory, SoftDeletes;

    public const STATUSES = ['todo', 'in_progress', 'done', 'blocked'];

    public const PRIORITIES = ['low', 'medium', 'high', 'urgent'];

    protected $fillable = [
        'user_id', 'project_id', 'milestone_id', 'work_log_id', 'title', 'notes',
        'status', 'priority', 'due_date', 'completed_at',
        'recurrence_rule', 'recurrence_interval', 'recurrence_days', 'recurrence_ends_at', 'parent_task_id',
    ];

    protected function casts(): array
    {
        return [
            'due_date' => 'date',
            'completed_at' => 'datetime',
            'recurrence_days' => 'array',
            'recurrence_ends_at' => 'date',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function milestone(): BelongsTo
    {
        return $this->belongsTo(Milestone::class);
    }

    public function workLog(): BelongsTo
    {
        return $this->belongsTo(WorkLog::class);
    }

    public function references(): MorphMany
    {
        return $this->morphMany(Reference::class, 'referenceable');
    }

    public function parentTask(): BelongsTo
    {
        return $this->belongsTo(Task::class, 'parent_task_id');
    }

    public function childTasks(): HasMany
    {
        return $this->hasMany(Task::class, 'parent_task_id');
    }

    public function scopeOwnedBy(Builder $query, User $user): Builder
    {
        return $query->where('user_id', $user->id);
    }

    public function nextDueDate(): ?Carbon
    {
        if (! $this->recurrence_rule || ! $this->due_date) {
            return null;
        }
        $base = $this->due_date->copy();

        return match ($this->recurrence_rule) {
            'daily' => $base->addDays($this->recurrence_interval),
            'weekly' => $base->addWeeks($this->recurrence_interval),
            'monthly' => $base->addMonths($this->recurrence_interval),
            'yearly' => $base->addYears($this->recurrence_interval),
            default => null,
        };
    }
}
