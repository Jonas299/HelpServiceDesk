<?php

namespace App\Domain\Tickets\Services;

use App\Domain\Tickets\Events\TicketActivity;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

final class TicketNotificationRecipients
{
    public function includes(TicketActivity $activity, Ticket $ticket, User $user): bool
    {
        if ($user->id === $activity->actorId || ! Gate::forUser($user)->allows('view', $ticket)) {
            return false;
        }
        if ($activity->internal && ! $user->isInternal()) {
            return false;
        }

        return $ticket->isParticipant($user)
            || ($activity->type === 'created' && $user->isInternal())
            || ($user->isInternal() && $user->id === ($activity->oldValues['assigned_to'] ?? null));
    }
}
