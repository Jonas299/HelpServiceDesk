<?php

namespace App\Policies;

use App\Models\TicketComment;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

class TicketCommentPolicy
{
    public function view(User $user, TicketComment $comment): bool
    {
        return $comment->ticket && Gate::forUser($user)->allows('view', $comment->ticket) && (! $comment->is_internal || $user->isInternal());
    }

    public function create(User $user): bool
    {
        return $user->isEnabled();
    }

    public function update(User $user, TicketComment $comment): bool
    {
        return false;
    }

    public function delete(User $user, TicketComment $comment): bool
    {
        return false;
    }
}
