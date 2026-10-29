<?php

namespace App\Domain\Tickets\Contracts;

use App\Models\Ticket;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

interface TicketRepositoryInterface
{
    /** @return Builder<Ticket> */
    public function visibleTo(User $user, array $filters = []): Builder;

    public function findVisible(User $user, string $publicId): Ticket;

    public function lock(Ticket $ticket): Ticket;

    public function create(array $attributes): Ticket;

    public function save(Ticket $ticket, array $attributes): Ticket;

    /** @return list<int> */
    public function observerIds(Ticket $ticket): array;

    public function replaceObservers(Ticket $ticket, array $userIds, int $actorId): void;
}
