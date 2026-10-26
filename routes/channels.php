<?php

use App\Models\Conversation;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

// Only participants may listen to a conversation.
Broadcast::channel('conversations.{conversationId}', function (User $user, int $conversationId): bool {
    return Conversation::query()->whereKey($conversationId)->first()?->hasParticipant($user) ?? false;
});
