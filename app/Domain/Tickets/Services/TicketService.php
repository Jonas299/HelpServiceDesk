<?php

namespace App\Domain\Tickets\Services;

use App\Domain\Companies\Contracts\CompanyRepositoryInterface;
use App\Domain\Tickets\Contracts\TicketRepositoryInterface;
use App\Domain\Tickets\DTOs\CreateTicketData;
use App\Domain\Tickets\Enums\TicketPriority;
use App\Domain\Tickets\Enums\TicketStatus;
use App\Domain\Tickets\Support\CommentContent;
use App\Domain\Users\Contracts\UserRepositoryInterface;
use App\Domain\Users\Enums\Role;
use App\Models\Ticket;
use App\Models\TicketCategory;
use App\Models\TicketComment;
use App\Models\User;
use App\Rules\ContactPhone;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class TicketService
{
    public function __construct(private TicketRepositoryInterface $tickets, private CompanyRepositoryInterface $companies, private UserRepositoryInterface $users, private CommentContent $commentContent, private TicketActivityRecorder $activity, private TicketObserverService $observers) {}

    public function create(User $actor, CreateTicketData $data): Ticket
    {
        Gate::forUser($actor)->authorize('create', Ticket::class);
        Validator::make(['subject' => $data->subject, 'description' => $data->description, 'contact_phone' => $data->contactPhone], ['subject' => 'required|string|max:255', 'description' => 'required|string|max:20000', 'contact_phone' => ['nullable', 'string', 'max:40', new ContactPhone]])->validate();
        $companyId = $actor->role === Role::Client ? $actor->company_id : $data->companyId;
        $this->companies->findActive($companyId);
        TicketCategory::query()->where('active', true)->findOrFail($data->categoryId);

        return DB::transaction(function () use ($actor, $data, $companyId) {
            // PostgreSQL transaction-scoped lock serializes number allocation, including soft-deleted tickets.
            DB::select('SELECT pg_advisory_xact_lock(?)', [20260001]);
            $year = now()->year;
            $prefix = "TK-{$year}-";
            $last = Ticket::withTrashed()->where('ticket_number', 'like', $prefix.'%')->orderByDesc('id')->value('ticket_number');
            $number = $last ? (int) substr($last, 8) + 1 : 1;
            $ticket = $this->tickets->create(['public_id' => (string) Str::ulid(), 'ticket_number' => $prefix.str_pad((string) $number, 6, '0', STR_PAD_LEFT), 'company_id' => $companyId, 'created_by' => $actor->id, 'category_id' => $data->categoryId, 'subject' => $data->subject, 'description' => $data->description, 'contact_phone' => $data->contactPhone, 'priority' => $data->priority, 'status' => TicketStatus::New, 'source' => $actor->isInternal() ? 'internal' : 'portal']);
            $observerIds = $this->observers->initialize($actor, $ticket, $data->observerIds);
            $this->record($ticket, $actor, 'created', [], ['status' => 'new', 'priority' => $data->priority->value, 'observer_ids' => $observerIds]);

            return $ticket;
        });
    }

    public function assign(User $actor, Ticket $ticket, int $agentId): Ticket
    {
        return DB::transaction(function () use ($actor, $ticket, $agentId) {
            $ticket = $this->tickets->lock($ticket);
            Gate::forUser($actor)->authorize('update', $ticket);
            $agent = $this->users->findAssignableAgent($agentId);
            if (! $ticket->status->allowsAssignment()) {
                throw ValidationException::withMessages(['assigned_to' => 'Este ticket no admite asignaciones.']);
            }
            if ($ticket->assigned_to === $agent->id) {
                return $ticket;
            }
            $old = ['assigned_to' => $ticket->assigned_to, 'status' => $ticket->status->value];
            $ticket = $this->tickets->save($ticket, ['assigned_to' => $agent->id, 'status' => $ticket->status === TicketStatus::New ? TicketStatus::Assigned : $ticket->status]);
            $this->record($ticket, $actor, $old['assigned_to'] ? 'reassigned' : 'assigned', $old, ['assigned_to' => $agent->id, 'status' => $ticket->status->value]);

            return $ticket;
        });
    }

    public function changePriority(User $actor, Ticket $ticket, TicketPriority $priority): Ticket
    {
        return DB::transaction(function () use ($actor, $ticket, $priority) {
            $ticket = $this->tickets->lock($ticket);
            Gate::forUser($actor)->authorize('update', $ticket);
            if ($ticket->priority === $priority) {
                return $ticket;
            }
            $old = ['priority' => $ticket->priority->value];
            $ticket = $this->tickets->save($ticket, ['priority' => $priority]);
            $this->record($ticket, $actor, 'priority_changed', $old, ['priority' => $priority->value]);

            return $ticket;
        });
    }

    public function transition(User $actor, Ticket $ticket, TicketStatus $status, ?string $reason = null): Ticket
    {
        return DB::transaction(function () use ($actor, $ticket, $status, $reason) {
            $ticket = $this->tickets->lock($ticket);
            Gate::forUser($actor)->authorize('update', $ticket);
            $allowed = $ticket->status->allowedTransitions();
            if (! in_array($status, $allowed, true)) {
                throw ValidationException::withMessages(['status' => 'Transición de estado no permitida.']);
            }
            if ($status === TicketStatus::Assigned && ! $ticket->assigned_to) {
                throw ValidationException::withMessages(['assigned_to' => 'Asigna un agente antes de cambiar a Asignado.']);
            }
            $reopening = $ticket->status === TicketStatus::Resolved && $status === TicketStatus::InProgress;
            if (in_array($status, [TicketStatus::Resolved, TicketStatus::Cancelled], true) || $reopening) {
                Validator::make(['reason' => $reason], ['reason' => 'required|string|min:5|max:10000'])->validate();
            }
            $old = ['status' => $ticket->status->value];
            $changes = ['status' => $status];
            if ($status === TicketStatus::Resolved) {
                $changes['resolved_at'] = now();
            }
            if ($status === TicketStatus::Closed) {
                $changes['closed_at'] = now();
            }
            if ($reopening) {
                $changes['resolved_at'] = null;
            }
            $type = $reopening ? 'reopened' : match ($status) {
                TicketStatus::Resolved => 'resolved',TicketStatus::Closed => 'closed',TicketStatus::Cancelled => 'cancelled',default => 'status_changed'
            };
            $ticket = $this->tickets->save($ticket, $changes);
            $this->record($ticket, $actor, $type, $old, ['status' => $status->value], ['reason' => $reason]);

            return $ticket;
        });
    }

    public function comment(User $actor, Ticket $ticket, string $body, bool $internal = false, bool $richText = false): TicketComment
    {
        return DB::transaction(function () use ($actor, $ticket, $body, $internal, $richText) {
            $ticket = $this->tickets->lock($ticket);
            Gate::forUser($actor)->authorize('comment', $ticket);
            if ($internal && ! $actor->isInternal()) {
                throw new AuthorizationException;
            }
            $body = $this->commentContent->prepare($body, $richText);
            $comment = $ticket->comments()->create(['user_id' => $actor->id, 'body' => $body, 'is_internal' => $internal, 'is_rich_text' => $richText]);
            if ($actor->isInternal() && ! $internal && ! $ticket->first_responded_at) {
                $this->tickets->save($ticket, ['first_responded_at' => now()]);
            }
            $this->record($ticket, $actor, 'commented', [], [], ['comment_id' => $comment->id, 'internal' => $internal], $internal);

            return $comment;
        });
    }

    public function record(Ticket $ticket, User $actor, string $type, array $old = [], array $new = [], array $metadata = [], bool $internal = false): void
    {
        $this->activity->record($ticket, $actor, $type, $old, $new, $metadata, $internal);
    }
}
