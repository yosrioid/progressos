<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Database\Factories\MilestoneFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Milestone extends Model
{
    /** @use HasFactory<MilestoneFactory> */
    use Auditable, HasFactory, SoftDeletes;

    public const STATUSES = ['active', 'paused', 'completed', 'cancelled'];
    public const TARGET_TYPES = ['count', 'duration', 'streak', 'custom'];

    public const SOURCE_TYPES = ['manual', 'work_log_count', 'work_log_minutes', 'learning_minutes', 'daily_progress_streak', 'task_done_count'];

    protected $fillable = ['user_id', 'title', 'category', 'target_type', 'source_type', 'source_filter', 'target_value', 'current_value', 'start_date', 'end_date', 'status', 'notes'];

    protected function casts(): array
    {
        return ['start_date' => 'date', 'end_date' => 'date', 'target_value' => 'decimal:2', 'current_value' => 'decimal:2'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function references(): MorphMany
    {
        return $this->morphMany(Reference::class, 'referenceable');
    }

    public function scopeOwnedBy(Builder $query, User $user): Builder
    {
        return $query->where('user_id', $user->id);
    }

    public function progressPercent(): int
    {
        if ((float) $this->target_value <= 0) {
            return 0;
        }

        return min(100, (int) round(((float) $this->current_value / (float) $this->target_value) * 100));
    }
}
