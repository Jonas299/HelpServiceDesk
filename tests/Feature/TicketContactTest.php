<?php

namespace Tests\Feature;

use App\Domain\Tickets\DTOs\CreateTicketData;
use App\Domain\Tickets\Services\TicketService;
use App\Domain\Users\Enums\Role;
use App\Filament\Client\Resources\Tickets\Pages\CreateTicket;
use App\Models\Ticket;
use App\Models\TicketCategory;
use App\Models\TicketEvent;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class TicketContactTest extends TestCase
{
    use RefreshDatabase;

    public function test_contact_phone_is_saved_and_visible_in_the_ticket(): void
    {
        Notification::fake();
        $client = User::factory()->create();
        $category = TicketCategory::factory()->create();
        $ticket = app(TicketService::class)->create($client, new CreateTicketData('Red sin conexión', 'Descripción de la incidencia', $category->id, contactPhone: '+1 (809) 555-0123 ext. 42'));
        $this->assertSame('+1 (809) 555-0123 ext. 42', $ticket->contact_phone);
        $this->actingAs($client)->get('/portal/tickets/'.$ticket->public_id)->assertOk()->assertSee('+1 (809) 555-0123 ext. 42');
    }

    public function test_invalid_phones_do_not_create_tickets_or_audit(): void
    {
        $client = User::factory()->create();
        $category = TicketCategory::factory()->create();
        foreach (['not-a-phone', '123', '+12345678901234567890', '<script>alert(1)</script>', "8095550123\nInjected"] as $phone) {
            try {
                app(TicketService::class)->create($client, new CreateTicketData('Problema', 'Descripción de prueba', $category->id, contactPhone: $phone));
                $this->fail('Invalid contact phone accepted');
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey('contact_phone', $exception->errors());
            }
        }
        $this->assertSame(0, Ticket::count());
        $this->assertSame(0, TicketEvent::count());
    }

    public function test_contact_remains_optional_for_existing_service_callers(): void
    {
        $client = User::factory()->create();
        $category = TicketCategory::factory()->create();
        $ticket = app(TicketService::class)->create($client, new CreateTicketData('Problema', 'Descripción de prueba', $category->id));
        $this->assertNull($ticket->contact_phone);
    }

    public function test_creation_form_accepts_a_contact_in_both_panels(): void
    {
        Notification::fake();
        $client = User::factory()->create();
        $admin = User::factory()->create(['role' => Role::Admin]);
        $category = TicketCategory::factory()->create();
        foreach (['client' => [$client, CreateTicket::class], 'admin' => [$admin, \App\Filament\Admin\Resources\Tickets\Pages\CreateTicket::class]] as $panel => [$user, $page]) {
            $this->actingAs($user);
            Filament::setCurrentPanel(Filament::getPanel($panel));
            Livewire::test($page)->fillForm([
                'company_id' => $client->company_id,
                'subject' => 'Ticket con contacto '.$panel,
                'description' => 'Información necesaria para el soporte',
                'category_id' => $category->id,
                'priority' => 'medium',
                'contact_phone' => '+1 809 555 0100',
            ])->call('create')->assertHasNoFormErrors();
            $this->assertDatabaseHas('tickets', ['subject' => 'Ticket con contacto '.$panel, 'contact_phone' => '+1 809 555 0100']);
        }
    }

    public function test_contact_is_not_visible_across_companies(): void
    {
        $client = User::factory()->create();
        $other = User::factory()->create();
        $ticket = Ticket::factory()->create(['company_id' => $client->company_id, 'created_by' => $client->id, 'contact_phone' => '809-555-0100']);
        $this->actingAs($other)->get('/portal/tickets/'.$ticket->public_id)->assertNotFound()->assertDontSee('809-555-0100');
    }
}
