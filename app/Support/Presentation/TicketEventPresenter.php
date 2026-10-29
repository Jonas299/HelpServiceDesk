<?php

namespace App\Support\Presentation;

use App\Domain\Tickets\Enums\TicketPriority;
use App\Domain\Tickets\Enums\TicketStatus;
use App\Models\TicketEvent;
use App\Models\User;
use Illuminate\Support\Collection;

final class TicketEventPresenter
{
    /** @param Collection<int, TicketEvent> $events */
    public function present(Collection $events, bool $internal): array
    {
        $participantIds = $events->flatMap(function (TicketEvent $event) use ($internal): array {
            return [
                ...($internal ? [$event->old_values['assigned_to'] ?? null, $event->new_values['assigned_to'] ?? null] : []),
                ...($event->old_values['observer_ids'] ?? []), ...($event->new_values['observer_ids'] ?? []),
            ];
        })->filter()->unique();
        $names = User::withTrashed()->whereIn('id', $participantIds)->pluck('name', 'id');

        return $events->map(function (TicketEvent $event) use ($internal, $names) {
            [$icon,$color] = match ($event->event_type) {
                'created' => ['sparkles', 'primary'], 'assigned','reassigned' => ['user-plus', 'info'],
                'priority_changed' => ['flag', 'warning'], 'resolved' => ['check-circle', 'success'],
                'closed' => ['lock-closed', 'gray'], 'cancelled' => ['x-circle', 'danger'],
                'reopened' => ['arrow-path', 'warning'], 'commented' => ['chat-bubble-left-right', 'info'],
                'observers_changed' => ['user-group', 'info'], 'attached' => ['paper-clip', 'gray'], default => ['arrows-right-left', 'primary'],
            };
            $changes = [];
            foreach (['status' => 'Estado', 'priority' => 'Prioridad', 'assigned_to' => 'Responsable', 'observer_ids' => 'Observadores'] as $field => $label) {
                if (! $internal && $field === 'assigned_to') {
                    continue;
                }
                if (! array_key_exists($field, $event->new_values ?? [])) {
                    continue;
                }
                $format = fn ($value) => match ($field) {
                    'status' => TicketStatus::tryFrom((string) $value)?->getLabel() ?? 'Sin estado',
                    'priority' => TicketPriority::tryFrom((string) $value)?->getLabel() ?? 'Sin prioridad',
                    'assigned_to' => $value ? ($names[$value] ?? 'Usuario archivado') : 'Sin asignar',
                    'observer_ids' => $value ? collect($value)->map(fn ($id) => $names[$id] ?? 'Usuario archivado')->implode(', ') : 'Sin observadores',
                };
                $old = $event->old_values[$field] ?? null;
                $new = $event->new_values[$field];
                if ($old !== $new) {
                    $changes[] = ['label' => $label, 'old' => $old === null ? null : $format($old), 'new' => $format($new)];
                }
            }

            return ['event' => $event, 'icon' => 'heroicon-o-'.$icon, 'color' => $color, 'changes' => $changes];
        })->all();
    }
}
