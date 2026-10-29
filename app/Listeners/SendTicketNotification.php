<?php

namespace App\Listeners;

use App\Domain\Tickets\Events\TicketActivity;
use App\Domain\Tickets\Services\TicketNotificationRecipients;
use App\Models\User;
use App\Notifications\TicketUpdated;
use Illuminate\Database\Eloquent\Builder;

class SendTicketNotification
{
    public function handle(TicketActivity $event): void
    {
        $ids = array_filter([...$event->ticket->participantIds(), $event->oldValues['assigned_to'] ?? null]);
        User::query()->where(function (Builder $query) use ($event, $ids) {
            $query->whereIn('id', $ids);
            if ($event->type === 'created') {
                $query->orWhereIn('role', ['admin', 'agent']);
            }
        })->get()->filter(fn (User $user) => app(TicketNotificationRecipients::class)->includes($event, $event->ticket, $user))
            ->each(fn (User $user) => $user->notify(new TicketUpdated($event)));
    }
}
