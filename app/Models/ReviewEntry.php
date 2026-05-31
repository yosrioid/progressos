<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ReviewEntry extends Model
{
    use Auditable, SoftDeletes;

    protected $fillable = ['user_id', 'period_type', 'period_start', 'period_end', 'answers', 'summary'];

    protected function casts(): array
    {
        return ['period_start' => 'date', 'period_end' => 'date', 'answers' => 'array'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
