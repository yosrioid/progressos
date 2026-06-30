<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InboxMessage extends Model
{
    protected $table = 'inbox_messages';

    protected $fillable = ['conversation_id', 'sender_id', 'body', 'type', 'file_path', 'file_name', 'file_size', 'file_mime', 'deleted_for_everyone_at'];

    protected function casts(): array
    {
        return ['deleted_for_everyone_at' => 'datetime'];
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(InboxConversation::class, 'conversation_id');
    }

    public function isDeletedForEveryone(): bool
    {
        return $this->deleted_for_everyone_at !== null;
    }
}
