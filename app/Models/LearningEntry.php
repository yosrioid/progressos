<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Database\Factories\LearningEntryFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class LearningEntry extends Model
{
    /** @use HasFactory<LearningEntryFactory> */
    use Auditable, HasFactory, SoftDeletes;

    public const CATEGORIES = ['programming', 'english', 'japanese', 'german', 'books', 'career', 'other'];

    public const SOURCE_TYPES = ['book', 'article', 'video', 'course', 'practice', 'podcast', 'other'];

    protected $fillable = ['user_id', 'date', 'topic', 'category', 'source_type', 'duration_minutes', 'progress_notes', 'takeaway', 'next_action', 'rating'];

    protected function casts(): array
    {
        return ['date' => 'date'];
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
}
