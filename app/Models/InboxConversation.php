<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InboxConversation extends Model
{
    protected $table = 'inbox_conversations';

    protected $fillable = ['user_one_id', 'user_two_id', 'last_message_at', 'user_one_read_at', 'user_two_read_at'];

    protected function casts(): array
    {
        return [
            'last_message_at' => 'datetime',
            'user_one_read_at' => 'datetime',
            'user_two_read_at' => 'datetime',
        ];
    }

    public function userOne(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_one_id');
    }

    public function userTwo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_two_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(InboxMessage::class, 'conversation_id');
    }

    /** @return User */
    public function otherUser(int $myId): User
    {
        $other = $this->user_one_id === $myId ? $this->userTwo : $this->userOne; // phpstan-ignore-line
        return $other instanceof User ? $other : abort(404);
    }

    public static function between(int $a, int $b): self
    {
        [$min, $max] = $a < $b ? [$a, $b] : [$b, $a];

        return self::firstOrCreate(['user_one_id' => $min, 'user_two_id' => $max]);
    }

    public function markRead(int $myId): void
    {
        $field = $this->user_one_id === $myId ? 'user_one_read_at' : 'user_two_read_at';
        $this->update([$field => now()]);
    }

    public function unreadCount(int $myId): int
    {
        $readAt = $this->user_one_id === $myId ? $this->user_one_read_at : $this->user_two_read_at;
        $query = $this->messages()->where('sender_id', '!=', $myId)->whereNull('deleted_for_everyone_at');
        if ($readAt) {
            $query->where('created_at', '>', $readAt);
        }

        return $query->count();
    }
}
