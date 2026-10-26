<?php

namespace App\Actions\Chat;

use App\Models\Conversation;
use App\Models\User;

class MarkConversationRead
{
    /**
     * Mark every message the other participant sent as read.
     */
    public function handle(Conversation $conversation, User $reader): int
    {
        return $conversation->messages()
            ->where('user_id', '!=', $reader->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);
    }
}
