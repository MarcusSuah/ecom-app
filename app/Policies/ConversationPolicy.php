<?php

namespace App\Policies;

use App\Models\Conversation;
use App\Models\User;

class ConversationPolicy
{
    /**
     * Only the two participants read or post in a conversation. Platform
     * admins pass every check through Gate::before.
     */
    public function view(User $user, Conversation $conversation): bool
    {
        return $conversation->hasParticipant($user);
    }

    public function send(User $user, Conversation $conversation): bool
    {
        return $conversation->hasParticipant($user);
    }
}
