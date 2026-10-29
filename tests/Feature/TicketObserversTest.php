<?php

namespace Tests\Feature;

use App\Domain\Tickets\Enums\TicketStatus;
use App\Domain\Tickets\Services\TicketObserverService;
use App\Domain\Tickets\Services\TicketService;
use App\Domain\Users\Enums\Role;
use App\Filament\Admin\Resources\Tickets\Pages\ViewTicket;
use App\Filament\Client\Resources\Tickets\Pages\ListTickets;
use App\Models\Ticket;
use App\Models\TicketObserver;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class TicketObserversTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $agent;

    private User $client;

    private User $observer;

    private Ticket $ticket;

    private TicketObserverService $observers;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        $this->admin = User::factory()->create(['role' => Role::Admin]);
        $this->agent = User::factory()->create(['role' => Role::Agent]);
        $this->client = User::factory()->create();
        $this->observer = User::factory()->create(['company_id' => $this->client->company_id]);
        $this->ticket = Ticket::factory()->create(['company_id' => $this->client->company_id, 'created_by' => $this->client->id]);
        $this->observers = app(TicketObserverService::class);
    }

    public function test_multiple_observers_are_saved_and_audited(): void
    {
        $this->observers->sync($this->admin, $this->ticket, [$this->agent->id, $this->observer->id]);
        $this->assertEqualsCanonicalizing([$this->agent->id, $this->observer->id], $this->ticket->observers()->pluck('users.id')->all());
        $this->assertDatabaseHas('ticket_observers', ['ticket_id' => $this->ticket->id, 'user_id' => $this->observer->id, 'added_by' => $this->admin->id, 'deleted_at' => null]);
        $event = $this->ticket->events()->firstOrFail();
        $this->assertSame('observers_changed', $event->event_type);
        $this->assertSame($this->admin->id, $event->actor_id);
        $this->assertEqualsCanonicalizing([$this->agent->id, $this->observer->id], $event->new_values['observer_ids']);
    }

    public function test_removal_is_soft_deleted_and_readding_restores_the_membership(): void
    {
        $this->observers->sync($this->admin, $this->ticket, [$this->observer->id]);
        $membership = $this->ticket->observerMemberships()->firstOrFail();
        $this->observers->sync($this->admin, $this->ticket, []);
        $this->assertSoftDeleted($membership);
        $this->assertCount(0, $this->ticket->observers()->get());
        $this->observers->sync($this->agent, $this->ticket, [$this->observer->id]);
        $this->assertNotSoftDeleted($membership);
        $this->assertSame($membership->id, $this->ticket->observerMemberships()->firstOrFail()->id);
        $this->assertSame($this->agent->id, $membership->fresh()->added_by);
        $this->assertSame(1, TicketObserver::withTrashed()->count());
        $this->assertSame(3, $this->ticket->events()->count());
    }

    public function test_unchanged_observers_do_not_duplicate_memberships_or_audit(): void
    {
        $this->observers->sync($this->admin, $this->ticket, [$this->agent->id, $this->observer->id]);
        $this->observers->sync($this->admin, $this->ticket, [$this->observer->id, $this->agent->id]);
        $this->assertSame(2, TicketObserver::count());
        $this->assertSame(1, $this->ticket->events()->count());
    }

    public function test_ineligible_or_malformed_selections_do_not_partially_update_the_ticket(): void
    {
        $foreign = User::factory()->create();
        $inactive = User::factory()->create(['role' => Role::Agent, 'active' => false]);
        $deleted = User::factory()->create(['role' => Role::Agent]);
        $deleted->delete();
        $this->observers->sync($this->admin, $this->ticket, [$this->observer->id]);
        foreach ([[$this->agent->id, $foreign->id], [$inactive->id], [$deleted->id], [999999], [$this->observer->id, $this->observer->id], ['invalid']] as $ids) {
            try {
                $this->observers->sync($this->admin, $this->ticket, $ids);
                $this->fail('An ineligible observer selection was accepted');
            } catch (ValidationException $exception) {
                $this->assertNotEmpty($exception->errors());
            }
            $this->assertSame([$this->observer->id], $this->ticket->observers()->pluck('users.id')->all());
        }
        $this->assertSame(1, $this->ticket->events()->count());
    }

    public function test_only_internal_staff_can_manage_observers(): void
    {
        $this->expectException(AuthorizationException::class);
        $this->observers->sync($this->client, $this->ticket, [$this->observer->id]);
    }

    public function test_options_exclude_other_companies_and_disabled_accounts(): void
    {
        $foreign = User::factory()->create();
        $inactive = User::factory()->create(['role' => Role::Agent, 'active' => false]);
        $options = collect($this->observers->options($this->admin, $this->ticket))->flatMap(fn ($group) => array_keys($group))->all();
        $this->assertContains($this->agent->id, $options);
        $this->assertContains($this->observer->id, $options);
        $this->assertNotContains($foreign->id, $options);
        $this->assertNotContains($inactive->id, $options);
    }

    public function test_observers_can_comment_without_gaining_management_permissions(): void
    {
        $this->observers->sync($this->admin, $this->ticket, [$this->observer->id, $this->agent->id]);
        $comment = app(TicketService::class)->comment($this->observer, $this->ticket, '<p>Participo como observador</p>', richText: true);
        $this->assertSame($this->observer->id, $comment->user_id);
        $this->assertFalse(Gate::forUser($this->observer)->allows('update', $this->ticket));
        $this->expectException(AuthorizationException::class);
        app(TicketService::class)->comment($this->observer, $this->ticket, 'Nota no autorizada', internal: true);
    }

    public function test_even_a_forged_membership_cannot_grant_cross_company_access(): void
    {
        $foreign = User::factory()->create();
        TicketObserver::factory()->create(['ticket_id' => $this->ticket->id, 'user_id' => $foreign->id, 'added_by' => $this->admin->id]);
        $this->assertFalse(Gate::forUser($foreign)->allows('view', $this->ticket));
        $this->assertFalse(Gate::forUser($foreign)->allows('comment', $this->ticket));
        $this->assertSame(0, Ticket::query()->visibleTo($foreign)->observedBy($foreign)->count());
        $this->actingAs($foreign)->get('/portal/tickets/'.$this->ticket->public_id)->assertNotFound();
    }

    public function test_participants_are_deduplicated_and_show_all_their_ticket_roles(): void
    {
        $this->ticket->update(['assigned_to' => $this->agent->id]);
        $this->observers->sync($this->admin, $this->ticket, [$this->client->id, $this->agent->id, $this->observer->id]);
        $participants = collect($this->observers->participants($this->client, $this->ticket))->keyBy(fn ($row) => $row['user']->id);
        $this->assertCount(3, $participants);
        $this->assertSame(['Solicitante', 'Observador'], $participants[$this->client->id]['roles']);
        $this->assertSame(['Responsable', 'Observador'], $participants[$this->agent->id]['roles']);
    }

    public function test_filament_multi_selection_updates_participants_and_timeline_without_losing_draft(): void
    {
        $this->actingAs($this->admin);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $page = Livewire::test(ViewTicket::class, ['record' => $this->ticket->public_id])
            ->fillForm(['body' => '<p>Borrador pendiente</p>'], 'composerForm');
        $draft = $page->get('commentDraft');
        $page->callAction('observers', ['observer_ids' => [$this->observer->id, $this->agent->id]])
            ->assertHasNoActionErrors()->assertSee($this->observer->name)->assertSee('Observadores actualizados');
        $this->assertSame($draft, $page->get('commentDraft'));
        $page->callAction('observers', ['observer_ids' => []])->assertHasNoActionErrors();
        $this->assertSame(0, TicketObserver::count());
    }

    public function test_client_observer_can_use_inline_composer_but_cannot_manage_members(): void
    {
        $this->observers->sync($this->admin, $this->ticket, [$this->observer->id]);
        $this->actingAs($this->observer);
        Filament::setCurrentPanel(Filament::getPanel('client'));
        Livewire::test(\App\Filament\Client\Resources\Tickets\Pages\ViewTicket::class, ['record' => $this->ticket->public_id])
            ->assertActionHidden('observers')
            ->fillForm(['body' => '<p>Respuesta del observador</p>'], 'composerForm')
            ->call('submitComment')->assertHasNoErrors();
        $this->assertDatabaseHas('ticket_comments', ['ticket_id' => $this->ticket->id, 'user_id' => $this->observer->id]);
    }

    public function test_observing_filter_excludes_removed_memberships_and_keeps_company_scope(): void
    {
        $this->observers->sync($this->admin, $this->ticket, [$this->observer->id]);
        $other = Ticket::factory()->create(['company_id' => $this->client->company_id]);
        $foreign = Ticket::factory()->create();
        TicketObserver::factory()->create(['ticket_id' => $foreign->id, 'user_id' => $this->observer->id, 'added_by' => $this->admin->id]);
        $this->actingAs($this->observer);
        Filament::setCurrentPanel(Filament::getPanel('client'));
        $page = Livewire::test(ListTickets::class)
            ->filterTable('observing')->assertCanSeeTableRecords([$this->ticket])->assertCanNotSeeTableRecords([$other, $foreign]);
        $this->observers->sync($this->admin, $this->ticket, []);
        $page->call('$refresh')->assertCanNotSeeTableRecords([$this->ticket]);
    }

    public function test_observer_membership_does_not_bypass_terminal_ticket_rules(): void
    {
        $this->observers->sync($this->admin, $this->ticket, [$this->observer->id]);
        $this->ticket->update(['status' => TicketStatus::Closed]);
        $this->assertTrue(Gate::forUser($this->observer)->allows('view', $this->ticket));
        $this->assertFalse(Gate::forUser($this->observer)->allows('comment', $this->ticket));
        $this->expectException(AuthorizationException::class);
        app(TicketService::class)->comment($this->observer, $this->ticket, 'No debe admitirse');
    }
}
