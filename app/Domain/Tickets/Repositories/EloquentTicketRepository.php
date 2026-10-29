<?php

namespace App\Domain\Tickets\Repositories;

use App\Domain\Tickets\Contracts\TicketRepositoryInterface;
use App\Models\Ticket;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

class EloquentTicketRepository implements TicketRepositoryInterface
{
    /** @return Builder<Ticket> */
    public function visibleTo(User $user, array $filters = []): Builder
    {
        $query = Ticket::query()->visibleTo($user)->with(['company', 'category', 'assignee']);
        foreach (['company_id', 'status', 'priority', 'category_id', 'assigned_to'] as $field) {
            if (isset($filters[$field])) {
                $query->where('tickets.'.$field, $filters[$field]);
            }
        }
        if (isset($filters['from'])) {
            $query->where('tickets.created_at', '>=', CarbonImmutable::parse($filters['from'], 'America/Santo_Domingo')->startOfDay()->utc());
        }
        if (isset($filters['until'])) {
            $query->where('tickets.created_at', '<', CarbonImmutable::parse($filters['until'], 'America/Santo_Domingo')->addDay()->startOfDay()->utc());
        }

        return $query;
    }

    public function findVisible(User $user, string $publicId): Ticket
    {
        return $this->visibleTo($user)->where('public_id', $publicId)->firstOrFail();
    }

    public function lock(Ticket $ticket): Ticket
    {
        return Ticket::query()->lockForUpdate()->findOrFail($ticket->id);
    }

    public function create(array $attributes): Ticket
    {
        return Ticket::create($attributes);
    }

    public function save(Ticket $ticket, array $attributes): Ticket
    {
        $ticket->fill($attributes)->save();

        return $ticket->refresh();
    }

    /** @return list<int> */
    public function observerIds(Ticket $ticket): array
    {
        return $ticket->observerMemberships()->orderBy('user_id')->pluck('user_id')->all();
    }

    public function replaceObservers(Ticket $ticket, array $userIds, int $actorId): void
    {
        $ticket->observerMemberships()->whereNotIn('user_id', $userIds)->delete();
        foreach ($userIds as $userId) {
            $membership = $ticket->observerMemberships()->withTrashed()->firstOrNew(['user_id' => $userId]);
            if (! $membership->exists) {
                $membership->fill(['added_by' => $actorId])->save();
            } elseif ($membership->trashed()) {
                $membership->fill(['added_by' => $actorId])->restore();
            }
        }
    }
}
