<?php

namespace App\Policies;

use App\Models\TicketAttachment;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

class TicketAttachmentPolicy
{
    public function view(User $user, TicketAttachment $attachment): bool
    {
        return ! $attachment->trashed() && $attachment->ticket && Gate::forUser($user)->allows('view', $attachment->ticket);
    }

    public function create(User $user): bool
    {
        return $user->isEnabled();
    }

    public function delete(User $user, TicketAttachment $attachment): bool
    {
        return false;
    }
}
