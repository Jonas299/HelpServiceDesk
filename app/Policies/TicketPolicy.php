<?php

namespace App\Policies;

use App\Domain\Users\Enums\Role;
use App\Models\Ticket;
use App\Models\User;

class TicketPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isEnabled();
    }

    public function view(User $user, Ticket $ticket): bool
    {
        return $user->isEnabled() && ! $ticket->trashed() && ($user->isInternal() || $ticket->company_id === $user->company_id);
    }

    public function create(User $user): bool
    {
        return $user->isEnabled();
    }

    public function update(User $user, Ticket $ticket): bool
    {
        return $this->view($user, $ticket) && $user->isInternal();
    }

    public function comment(User $user, Ticket $ticket): bool
    {
        return $this->view($user, $ticket) && ! in_array($ticket->status->value, ['closed', 'cancelled'], true);
    }

    public function delete(User $user, Ticket $ticket): bool
    {
        return $user->isEnabled() && $user->role === Role::Admin && in_array($ticket->status->value, ['closed', 'cancelled'], true);
    }

    public function restore(User $user, Ticket $ticket): bool
    {
        return $user->isEnabled() && $user->role === Role::Admin;
    }

    public function forceDelete(User $user, Ticket $ticket): bool
    {
        return false;
    }

    public function manageObservers(User $user, Ticket $ticket): bool
    {
        return $this->update($user, $ticket);
    }
}
