<?php

namespace App\Domain\Tickets\Services;

use App\Domain\Tickets\Events\TicketActivity;
use App\Models\Ticket;
use App\Models\User;

final class TicketActivityRecorder
{
    public function record(Ticket $ticket, User $actor, string $type, array $old = [], array $new = [], array $metadata = [], bool $internal = false): void
    {
        $ticket->events()->create(['actor_id' => $actor->id, 'event_type' => $type, 'old_values' => $old, 'new_values' => $new, 'metadata' => $metadata]);
        TicketActivity::dispatch($ticket, $type, $actor->id, $internal, $old, $new, $metadata);
    }
}
