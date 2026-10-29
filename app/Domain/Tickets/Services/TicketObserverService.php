<?php

namespace App\Domain\Tickets\Services;

use App\Domain\Tickets\Contracts\TicketRepositoryInterface;
use App\Domain\Users\Contracts\UserRepositoryInterface;
use App\Domain\Users\Enums\Role;
use App\Models\Company;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

final class TicketObserverService
{
    public const int MAX_OBSERVERS = 200;

    public function __construct(private TicketRepositoryInterface $tickets, private UserRepositoryInterface $users, private TicketActivityRecorder $activity) {}

    public function sync(User $actor, Ticket $ticket, array $observerIds): Ticket
    {
        return DB::transaction(function () use ($actor, $ticket, $observerIds) {
            $ticket = $this->tickets->lock($ticket);
            Gate::forUser($actor)->authorize('manageObservers', $ticket);

            return $this->persist($actor, $ticket, $this->validatedIds($actor, $ticket->company_id, $observerIds));
        });
    }

    /** Called only inside the ticket creation transaction, before its initial audit event. */
    public function initialize(User $actor, Ticket $ticket, array $observerIds): array
    {
        Gate::forUser($actor)->authorize('create', Ticket::class);
        Gate::forUser($actor)->authorize('view', $ticket);
        if (! $ticket->wasRecentlyCreated || $ticket->created_by !== $actor->id || $ticket->events()->exists()) {
            throw new \LogicException('Los observadores iniciales solo se definen al crear el ticket.');
        }
        $ids = $this->validatedIds($actor, $ticket->company_id, $observerIds);
        $this->tickets->replaceObservers($ticket, $ids, $actor->id);

        return $ids;
    }

    public function remove(User $actor, Ticket $ticket, int $userId): Ticket
    {
        return DB::transaction(function () use ($actor, $ticket, $userId) {
            $ticket = $this->tickets->lock($ticket);
            Gate::forUser($actor)->authorize($actor->id === $userId ? 'view' : 'manageObservers', $ticket);

            return $this->persist($actor, $ticket, array_values(array_diff($this->tickets->observerIds($ticket), [$userId])));
        });
    }

    public function setSubscription(User $actor, Ticket $ticket, bool $subscribed): Ticket
    {
        return DB::transaction(function () use ($actor, $ticket, $subscribed) {
            $ticket = $this->tickets->lock($ticket);
            Gate::forUser($actor)->authorize('view', $ticket);
            $ids = $this->tickets->observerIds($ticket);
            $ids = $subscribed ? array_values(array_unique([...$ids, $actor->id])) : array_values(array_diff($ids, [$actor->id]));
            if (count($ids) > self::MAX_OBSERVERS) {
                throw ValidationException::withMessages(['observer_ids' => 'Este ticket alcanzó el máximo de observadores.']);
            }

            return $this->persist($actor, $ticket, $ids);
        });
    }

    private function persist(User $actor, Ticket $ticket, array $ids): Ticket
    {
        sort($ids);
        $old = $this->tickets->observerIds($ticket);
        if ($old === $ids) {
            return $ticket;
        }
        $this->tickets->replaceObservers($ticket, $ids, $actor->id);
        $ticket->touch();
        $this->activity->record($ticket, $actor, 'observers_changed', ['observer_ids' => $old], ['observer_ids' => $ids]);

        return $ticket;
    }

    /** @return Builder<User> */
    private function candidates(User $actor, int $companyId): Builder
    {
        $query = $this->users->eligibleObservers($companyId);

        return $actor->isInternal() ? $query : $query->where('role', Role::Client->value)->where('company_id', $actor->company_id);
    }

    private function validatedIds(User $actor, int $companyId, array $observerIds): array
    {
        Validator::make(['observer_ids' => $observerIds], ['observer_ids' => 'array|max:'.self::MAX_OBSERVERS, 'observer_ids.*' => 'required|integer|min:1|distinct'])->validate();
        $ids = array_map(intval(...), array_values($observerIds));
        sort($ids);
        if ($this->candidates($actor, $companyId)->whereIn('id', $ids)->count() !== count($ids)) {
            throw ValidationException::withMessages(['observer_ids' => 'Selecciona observadores activos autorizados para la empresa del ticket.']);
        }

        return $ids;
    }

    /** @param Builder<User> $query */
    private function groupedOptions(Builder $query): array
    {
        return $query->orderBy('name')->get()
            ->groupBy(fn (User $user) => $user->isInternal() ? 'Equipo de soporte' : 'Clientes de la empresa')
            ->map(fn ($users) => $users->mapWithKeys(fn (User $user) => [$user->id => $user->name.' · '.$user->email])->all())->all();
    }

    public function options(User $actor, Ticket $ticket): array
    {
        Gate::forUser($actor)->authorize('manageObservers', $ticket);

        return $this->groupedOptions($this->candidates($actor, $ticket->company_id));
    }

    public function creationOptions(User $actor, ?int $companyId): array
    {
        Gate::forUser($actor)->authorize('create', Ticket::class);
        $companyId = $actor->isInternal() ? $companyId : $actor->company_id;
        if (! $companyId || ! Company::query()->where('active', true)->whereKey($companyId)->exists()) {
            return [];
        }

        return $this->groupedOptions($this->candidates($actor, $companyId));
    }

    public function selectedIds(User $actor, Ticket $ticket): array
    {
        Gate::forUser($actor)->authorize('manageObservers', $ticket);

        return $this->users->eligibleObservers($ticket->company_id)->whereIn('id', $this->tickets->observerIds($ticket))->pluck('id')->all();
    }

    public function participants(User $actor, Ticket $ticket): array
    {
        Gate::forUser($actor)->authorize('view', $ticket);
        $observers = $this->tickets->observerIds($ticket);

        return $this->users->eligibleObservers($ticket->company_id)->whereIn('id', $ticket->participantIds())->orderBy('name')->get()->map(function (User $user) use ($ticket, $observers) {
            $roles = [];
            if ($user->id === $ticket->created_by) {
                $roles[] = 'Solicitante';
            }
            if ($user->id === $ticket->assigned_to) {
                $roles[] = 'Responsable';
            }
            if (in_array($user->id, $observers, true)) {
                $roles[] = 'Observador';
            }

            return ['user' => $user, 'roles' => $roles];
        })->all();
    }
}
