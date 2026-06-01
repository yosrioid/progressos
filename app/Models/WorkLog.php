<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Database\Factories\WorkLogFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class WorkLog extends Model
{
    /** @use HasFactory<WorkLogFactory> */
    use Auditable, HasFactory, SoftDeletes;

    public const CATEGORIES = ['bug', 'feature', 'research', 'testing', 'setup', 'meeting', 'documentation', 'refactor', 'other'];

    public const STATUSES = ['todo', 'in_progress', 'done', 'blocked'];

    public const PRIORITIES = ['low', 'medium', 'high', 'urgent'];

    protected $fillable = ['user_id', 'project_id', 'date', 'project_name', 'ticket_code', 'title', 'category', 'status', 'priority', 'description', 'resolution_or_outcome', 'estimated_duration', 'actual_duration'];

    protected function casts(): array
    {
        return ['date' => 'date'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(WorkLogTag::class, 'work_log_tag');
    }

    public function references(): MorphMany
    {
        return $this->morphMany(Reference::class, 'referenceable');
    }

    public function scopeOwnedBy(Builder $query, User $user): Builder
    {
        return $query->where('user_id', $user->id);
    }
}
