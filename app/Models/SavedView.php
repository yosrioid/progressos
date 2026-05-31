<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class SavedView extends Model
{
    use Auditable, SoftDeletes;

    protected $fillable = ['user_id', 'module', 'name', 'filters', 'pinned'];

    protected function casts(): array
    {
        return ['filters' => 'array', 'pinned' => 'boolean'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
