<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $path
 */
class DocFile extends Model
{
    protected $fillable = ['doc_id', 'user_id', 'original_name', 'path', 'mime_type', 'size'];

    public function doc(): BelongsTo
    {
        return $this->belongsTo(Doc::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
