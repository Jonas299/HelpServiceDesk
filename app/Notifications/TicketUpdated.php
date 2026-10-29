<?php

namespace App\Notifications;

use App\Domain\Tickets\Enums\TicketPriority;
use App\Domain\Tickets\Enums\TicketStatus;
use App\Domain\Tickets\Events\TicketActivity;
use App\Domain\Tickets\Services\TicketNotificationRecipients;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TicketUpdated extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public TicketActivity $activity)
    {
        $this->afterCommit();
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function shouldSend(object $notifiable, string $channel): bool
    {
        if (! $notifiable instanceof User) {
            return false;
        }
        $user = $notifiable->fresh();
        $ticket = Ticket::query()->find($this->activity->ticket->id);

        return $user !== null && $ticket !== null
            && app(TicketNotificationRecipients::class)->includes($this->activity, $ticket, $user);
    }

    public function toMail(object $notifiable): MailMessage
    {
        $path = $notifiable->isInternal() ? 'admin' : 'portal';

        $title = match ($this->activity->type) {
            'created' => 'Se creó un nuevo ticket',
            'assigned' => 'Ticket asignado',
            'reassigned' => 'Ticket reasignado',
            'priority_changed' => 'Prioridad del ticket modificada',
            'status_changed' => 'Estado del ticket modificado',
            'resolved' => 'Ticket resuelto',
            'closed' => 'Ticket cerrado',
            'cancelled' => 'Ticket cancelado',
            'reopened' => 'Ticket reabierto',
            'commented' => $this->activity->internal ? 'Nueva nota interna en el ticket' : 'Nuevo comentario en el ticket',
            'attached' => 'Nuevo archivo adjunto en el ticket',
            'observers_changed' => 'Observadores del ticket actualizados',
            default => 'Actividad en el ticket',
        };
        $message = (new MailMessage)->subject($title.' · '.$this->activity->ticket->ticket_number)
            ->line($title.': '.$this->activity->ticket->ticket_number.' — '.$this->activity->ticket->subject);
        foreach (['status' => TicketStatus::class, 'priority' => TicketPriority::class] as $field => $enum) {
            if (isset($this->activity->newValues[$field])) {
                $old = isset($this->activity->oldValues[$field]) ? $enum::from($this->activity->oldValues[$field])->getLabel().' → ' : '';
                $message->line(($field === 'status' ? 'Estado: ' : 'Prioridad: ').$old.$enum::from($this->activity->newValues[$field])->getLabel());
            }
        }
        if (isset($this->activity->newValues['assigned_to'])) {
            $message->line('Responsable: '.(User::withTrashed()->whereKey($this->activity->newValues['assigned_to'])->value('name') ?? 'Sin asignar'));
        }
        if (filled($this->activity->metadata['reason'] ?? null)) {
            $message->line('Motivo: '.$this->activity->metadata['reason']);
        }

        return $message->action('Ver ticket', url($path.'/tickets/'.$this->activity->ticket->public_id));
    }
}
