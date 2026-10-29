<?php

namespace Tests\Feature;

use App\Domain\Tickets\DTOs\CreateTicketData;
use App\Domain\Tickets\Services\TicketObserverService;
use App\Domain\Tickets\Services\TicketService;
use App\Domain\Users\Enums\Role;
use App\Filament\Admin\Resources\Tickets\Pages\ViewTicket;
use App\Filament\Client\Resources\Tickets\Pages\CreateTicket;
use App\Models\Ticket;
use App\Models\TicketCategory;
use App\Models\TicketEvent;
use App\Models\TicketObserver;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class InteractiveParticipantsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $client;

    private User $observer;

    private TicketCategory $category;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        $this->admin = User::factory()->create(['role' => Role::Admin]);
        $this->client = User::factory()->create();
        $this->observer = User::factory()->create(['company_id' => $this->client->company_id]);
        $this->category = TicketCategory::factory()->create();
    }

    private function ticket(): Ticket
    {
        return app(TicketService::class)->create($this->client, new CreateTicketData('Solicitud de prueba', 'Descripción completa', $this->category->id));
    }

    public function test_creation_saves_contact_and_multiple_observers_in_one_aggregate(): void
    {
        $ticket = app(TicketService::class)->create($this->admin, new CreateTicketData('Prueba', 'Descripción completa', $this->category->id, companyId: $this->client->company_id, contactPhone: '809-555-0100', observerIds: [$this->client->id, $this->observer->id]));
        $this->assertSame('809-555-0100', $ticket->contact_phone);
        $this->assertCount(2, $ticket->observers()->get());
        $this->assertSame(1, $ticket->events()->count());
        $this->assertEqualsCanonicalizing([$this->client->id, $this->observer->id], $ticket->events()->firstOrFail()->new_values['observer_ids']);
    }

    public function test_invalid_creation_observers_roll_back_the_ticket_and_history(): void
    {
        foreach ([$this->admin, User::factory()->create()] as $invalid) {
            try {
                app(TicketService::class)->create($this->client, new CreateTicketData('Prueba', 'Descripción completa', $this->category->id, observerIds: [$this->observer->id, $invalid->id]));
                $this->fail('Unauthorized initial observer accepted');
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey('observer_ids', $exception->errors());
            }
        }
        $this->assertSame(0, Ticket::count());
        $this->assertSame(0, TicketObserver::count());
        $this->assertSame(0, TicketEvent::count());
    }

    public function test_client_creation_options_only_show_its_own_company_even_with_forged_company_id(): void
    {
        $foreign = User::factory()->create();
        $options = app(TicketObserverService::class)->creationOptions($this->client, $foreign->company_id);
        $ids = collect($options)->flatMap(fn ($group) => array_keys($group))->all();
        $this->assertContains($this->observer->id, $ids);
        $this->assertNotContains($foreign->id, $ids);
        $this->assertNotContains($this->admin->id, $ids);
    }

    public function test_creation_form_submits_multiple_observers_in_both_panels(): void
    {
        foreach (['client' => [$this->client, CreateTicket::class], 'admin' => [$this->admin, \App\Filament\Admin\Resources\Tickets\Pages\CreateTicket::class]] as $panel => [$actor, $page]) {
            $this->actingAs($actor);
            Filament::setCurrentPanel(Filament::getPanel($panel));
            Livewire::test($page)->fillForm([
                'company_id' => $this->client->company_id,
                'subject' => 'Creación con observadores '.$panel,
                'description' => 'Descripción completa para soporte',
                'priority' => 'medium',
                'category_id' => $this->category->id,
                'observer_ids' => [$this->client->id, $this->observer->id],
            ])->call('create')->assertHasNoFormErrors();
            $ticket = Ticket::where('subject', 'Creación con observadores '.$panel)->firstOrFail();
            $this->assertSame(2, $ticket->observerMemberships()->count());
        }
    }

    public function test_inline_remove_preserves_draft_and_original_creator(): void
    {
        $ticket = $this->ticket();
        app(TicketObserverService::class)->sync($this->admin, $ticket, [$this->client->id, $this->observer->id]);
        $this->actingAs($this->admin);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $page = Livewire::test(ViewTicket::class, ['record' => $ticket->public_id])->fillForm(['body' => '<p>Borrador pendiente</p>'], 'composerForm');
        $draft = $page->get('commentDraft');
        $page->call('removeObserver', $this->client->id)->assertHasNoErrors();
        $this->assertFalse($ticket->isObserver($this->client));
        $this->assertSame($this->client->id, $ticket->fresh()->created_by);
        $this->assertSame($draft, $page->get('commentDraft'));
    }

    public function test_users_can_subscribe_and_unsubscribe_from_the_participants_section(): void
    {
        $ticket = $this->ticket();
        $this->actingAs($this->observer);
        Filament::setCurrentPanel(Filament::getPanel('client'));
        $page = Livewire::test(\App\Filament\Client\Resources\Tickets\Pages\ViewTicket::class, ['record' => $ticket->public_id]);
        $page->call('setObserverSubscription', true)->assertHasNoErrors()->assertSee('Dejar de observar');
        $this->assertTrue($ticket->isObserver($this->observer));
        $page->call('setObserverSubscription', true)->assertHasNoErrors();
        $this->assertSame(2, $ticket->events()->count()); // Created plus one subscription event.
        $page->call('removeObserver', $this->observer->id)->assertHasNoErrors()->assertSee('Seguir');
        $this->assertFalse($ticket->isObserver($this->observer));
    }

    public function test_client_cannot_remove_another_observer_via_manipulated_inline_action(): void
    {
        $ticket = $this->ticket();
        app(TicketObserverService::class)->sync($this->admin, $ticket, [$this->observer->id]);
        $this->expectException(AuthorizationException::class);
        app(TicketObserverService::class)->remove($this->client, $ticket, $this->observer->id);
    }

    public function test_subscription_never_grants_cross_company_access(): void
    {
        $ticket = $this->ticket();
        $other = User::factory()->create();
        $this->expectException(AuthorizationException::class);
        app(TicketObserverService::class)->setSubscription($other, $ticket, true);
    }

    public function test_initialization_cannot_be_used_to_edit_an_existing_ticket(): void
    {
        $ticket = $this->ticket();
        $this->expectException(\LogicException::class);
        app(TicketObserverService::class)->initialize($this->client, $ticket, [$this->observer->id]);
    }
}
