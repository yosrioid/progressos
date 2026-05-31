<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Database\Factories\DailyProgressEntryFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class DailyProgressEntry extends Model
{
    /** @use HasFactory<DailyProgressEntryFactory> */
    use Auditable, HasFactory, SoftDeletes;

    protected $fillable = ['user_id', 'date', 'title', 'in_progress', 'todo', 'blockers', 'notes', 'completed_items', 'mood', 'archived'];

    protected function casts(): array
    {
        return ['date' => 'date', 'completed_items' => 'array', 'archived' => 'boolean'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(DailyProgressTag::class, 'daily_progress_entry_tag');
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
