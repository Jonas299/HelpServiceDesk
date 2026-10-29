<?php

namespace Tests\Feature;

use App\Domain\Tickets\Events\TicketActivity;
use App\Domain\Users\Enums\Role;
use App\Filament\Admin\Resources\Clients\Pages\ManageClients;
use App\Filament\Admin\Resources\Users\Pages\ManageUsers;
use App\Listeners\SendTicketNotification;
use App\Models\Ticket;
use App\Models\User;
use App\Notifications\TicketUpdated;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\TestCase;

class RequestedConfigurationTest extends TestCase
{
    use RefreshDatabase;

    public function test_demo_seeder_sets_password_for_all_demo_users_on_every_run(): void
    {
        Notification::fake();
        $this->seed();
        $users = User::query()->get();
        $this->assertCount(6, $users);
        foreach ($users as $user) {
            $this->assertTrue(Hash::check('password', $user->password));
        }
        $users->first()->update(['password' => 'previous-password']);
        $this->seed();
        $this->assertTrue(Hash::check('password', $users->first()->fresh()->password));
        $this->assertSame(6, User::count());
    }

    public function test_clients_and_staff_have_separate_tables_and_company_column(): void
    {
        $admin = User::factory()->create(['role' => Role::Admin]);
        $client = User::factory()->create();
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs($admin);
        Livewire::test(ManageClients::class)->assertCanSeeTableRecords([$client])->assertCanNotSeeTableRecords([$admin])->assertTableColumnExists('company.name');
        Livewire::test(ManageUsers::class)->assertCanSeeTableRecords([$admin])->assertCanNotSeeTableRecords([$client]);
    }

    public function test_new_ticket_notifies_staff_and_excludes_unrelated_clients_and_actor(): void
    {
        Notification::fake();
        $ticket = Ticket::factory()->create();
        $agent = User::factory()->create(['role' => Role::Agent]);
        $otherClient = User::factory()->create();
        $activity = new TicketActivity($ticket, 'created', $ticket->created_by);
        app(SendTicketNotification::class)->handle($activity);
        Notification::assertSentTo($agent, TicketUpdated::class);
        Notification::assertNotSentTo($otherClient, TicketUpdated::class);
        Notification::assertNotSentTo($ticket->creator, TicketUpdated::class);
        $notification = new TicketUpdated($activity);
        $this->assertTrue($notification->shouldSend($agent, 'mail'));
        $agent->update(['active' => false]);
        $this->assertFalse($notification->shouldSend($agent, 'mail'));
    }

    public function test_messages_capture_specific_change_and_internal_notes_stay_private(): void
    {
        Notification::fake();
        $agent = User::factory()->create(['role' => Role::Agent]);
        $ticket = Ticket::factory()->create(['assigned_to' => $agent->id]);
        $actor = User::factory()->create(['role' => Role::Admin]);
        $activity = new TicketActivity($ticket, 'priority_changed', $actor->id, false, ['priority' => 'low'], ['priority' => 'critical']);
        $mail = (new TicketUpdated($activity))->toMail($agent);
        $this->assertStringContainsString('Prioridad del ticket modificada', $mail->subject);
        $this->assertContains('Prioridad: Baja → Crítica', $mail->introLines);
        $note = new TicketActivity($ticket, 'commented', $actor->id, true);
        app(SendTicketNotification::class)->handle($note);
        Notification::assertSentTo($agent, TicketUpdated::class);
        Notification::assertNotSentTo($ticket->creator, TicketUpdated::class);
    }
}
