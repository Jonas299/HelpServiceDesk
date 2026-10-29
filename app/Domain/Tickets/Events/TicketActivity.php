<?php

namespace App\Domain\Tickets\Events;

use App\Models\Ticket;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

final class TicketActivity implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(public Ticket $ticket, public string $type, public int $actorId, public bool $internal = false, public array $oldValues = [], public array $newValues = [], public array $metadata = []) {}
}
