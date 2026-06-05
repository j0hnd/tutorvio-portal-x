<?php

namespace App\Policies;

use App\Models\Conversation;
use App\Models\User;

class ConversationPolicy
{
    /**
     * Determine whether the user can list conversations.
     *
     * Students and teachers may list their participant conversations. Admins are
     * allowed by policy. Staff need `messages.view` or `messages.manage`.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['admin', 'teacher', 'student'])
            || ($user->hasRole('staff') && ($user->can('messages.view') || $user->can('messages.manage')));
    }

    /**
     * Determine whether the user can view a specific conversation.
     *
     * Row visibility is still constrained by the controller query, so
     * non-global users only receive records where they are active participants.
     */
    public function view(User $user, Conversation $conversation): bool
    {
        if ($this->viewAll($user)) {
            return true;
        }

        return $conversation->participants()
            ->where('user_id', $user->id)
            ->whereNull('archived_at')
            ->exists();
    }

    /**
     * Determine whether the user can create conversations.
     */
    public function create(User $user): bool
    {
        return $user->hasAnyRole(['admin', 'teacher', 'student'])
            || ($user->hasRole('staff') && ($user->can('messages.view') || $user->can('messages.manage')));
    }

    /**
     * Determine whether the user can view conversations beyond participant rows.
     */
    public function viewAll(User $user): bool
    {
        return $user->hasRole('admin')
            || ($user->hasRole('staff') && ($user->can('messages.view') || $user->can('messages.manage')));
    }

    /**
     * Determine whether the user can manage conversation-level actions.
     */
    public function manage(User $user, ?Conversation $conversation = null): bool
    {
        return $user->hasRole('admin')
            || ($user->hasRole('staff') && $user->can('messages.manage'));
    }

    /**
     * Determine whether the user can pin or unpin messages in a conversation.
     */
    public function pinMessage(User $user, Conversation $conversation): bool
    {
        if ($this->manage($user, $conversation)) {
            return true;
        }

        if ($conversation->status !== Conversation::STATUS_ACTIVE) {
            return false;
        }

        if (! $this->view($user, $conversation)) {
            return false;
        }

        if ($user->hasRole('teacher') && (bool) config('chat.allow_teacher_message_pins', true)) {
            return (int) $conversation->teacher_id === (int) $user->id;
        }

        if ($user->hasRole('student') && (bool) config('chat.allow_student_message_pins', false)) {
            return (int) $conversation->student_id === (int) $user->id;
        }

        return false;
    }
}
