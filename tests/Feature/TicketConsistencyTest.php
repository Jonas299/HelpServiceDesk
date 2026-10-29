<?php

namespace Tests\Feature;

use App\Domain\Tickets\DTOs\CreateTicketData;
use App\Domain\Tickets\Events\TicketActivity;
use App\Domain\Tickets\Services\TicketObserverService;
use App\Domain\Tickets\Services\TicketService;
use App\Domain\Users\Enums\Role;
use App\Models\Ticket;
use App\Models\TicketCategory;
use App\Models\TicketEvent;
use App\Models\User;
use App\Notifications\TicketUpdated;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class TicketConsistencyTest extends TestCase
{
    use DatabaseMigrations;

    public function test_concurrent_creations_have_unique_sequential_numbers(): void
    {
        $client = User::factory()->create();
        $category = TicketCategory::factory()->create();
        $processes = [];
        for ($i = 0; $i < 6; $i++) {
            $process = new Process([PHP_BINARY, base_path('tests/Support/create-ticket.php'), (string) $client->id, (string) $category->id], base_path(), ['APP_ENV' => 'testing', 'DB_CONNECTION' => 'pgsql', 'DB_DATABASE' => 'helpdesk_testing', 'CACHE_STORE' => 'array', 'QUEUE_CONNECTION' => 'sync', 'MAIL_MAILER' => 'array', 'SESSION_DRIVER' => 'array']);
            $process->setTimeout(30);
            $process->start();
            $processes[] = $process;
        }
        $numbers = [];
        foreach ($processes as $process) {
            $process->wait();
            $this->assertTrue($process->isSuccessful(), $process->getErrorOutput());
            $numbers[] = trim($process->getOutput());
        }
        $this->assertCount(6, array_unique($numbers));
        $this->assertSame(6, Ticket::count());
        $this->assertSame(6, TicketEvent::count());
        sort($numbers);
        $this->assertStringEndsWith('000001', $numbers[0]);
        $this->assertStringEndsWith('000006', $numbers[5]);
    }

    public function test_notifications_are_dispatched_after_commit_and_internal_notes_remain_private(): void
    {
        Notification::fake();
        $client = User::factory()->create();
        $agent = User::factory()->create(['role' => Role::Agent]);
        $category = TicketCategory::factory()->create();
        $service = app(TicketService::class);
        $ticket = $service->create($client, new CreateTicketData('Correo caído', 'No puedo enviar correos.', $category->id));
        Notification::assertSentTo($agent, TicketUpdated::class);
        Notification::fake();
        DB::beginTransaction();
        $service->assign($agent, $ticket, $agent->id);
        Notification::assertNothingSent();
        DB::commit();
        Notification::assertSentTo($client, TicketUpdated::class);
        Notification::fake();
        $service->comment($agent, $ticket, 'Información estrictamente interna', true);
        Notification::assertNothingSent();
        $service->comment($agent, $ticket, 'Ya estamos revisando.');
        Notification::assertSentTo($client, TicketUpdated::class);
    }

    public function test_rollback_discards_ticket_audit_and_notification(): void
    {
        Notification::fake();
        $client = User::factory()->create();
        $category = TicketCategory::factory()->create();
        DB::beginTransaction();
        app(TicketService::class)->create($client, new CreateTicketData('Solicitud transaccional', 'Debe revertirse.', $category->id));
        DB::rollBack();
        $this->assertSame(0, Ticket::count());
        $this->assertSame(0, TicketEvent::count());
        Notification::assertNothingSent();
    }

    public function test_observers_receive_committed_public_updates_once_and_no_internal_notes(): void
    {
        Notification::fake();
        $admin = User::factory()->create(['role' => Role::Admin]);
        $client = User::factory()->create();
        $observer = User::factory()->create(['company_id' => $client->company_id]);
        $agent = User::factory()->create(['role' => Role::Agent]);
        $ticket = Ticket::factory()->create(['company_id' => $client->company_id, 'created_by' => $client->id, 'assigned_to' => $agent->id]);
        DB::beginTransaction();
        app(TicketObserverService::class)->sync($admin, $ticket, [$client->id, $agent->id, $observer->id]);
        Notification::assertNothingSent();
        DB::commit();
        Notification::assertSentTo([$client, $agent, $observer], TicketUpdated::class);
        Notification::assertSentTimes(TicketUpdated::class, 3);
        Notification::fake();
        app(TicketService::class)->comment($agent, $ticket, 'Actualización pública');
        Notification::assertSentTo([$client, $observer], TicketUpdated::class);
        Notification::assertNotSentTo($agent, TicketUpdated::class);
        Notification::assertSentTimes(TicketUpdated::class, 2);
        Notification::fake();
        app(TicketService::class)->comment($agent, $ticket, 'Información privada', internal: true);
        Notification::assertNothingSent();
    }

    public function test_queued_observer_notification_rechecks_membership_and_company_at_delivery(): void
    {
        Notification::fake();
        $admin = User::factory()->create(['role' => Role::Admin]);
        $client = User::factory()->create();
        $observer = User::factory()->create(['company_id' => $client->company_id]);
        $ticket = Ticket::factory()->create(['company_id' => $client->company_id, 'created_by' => $client->id]);
        $service = app(TicketObserverService::class);
        $service->sync($admin, $ticket, [$observer->id]);
        $notification = new TicketUpdated(new TicketActivity($ticket, 'commented', $admin->id));
        $this->assertTrue($notification->shouldSend($observer, 'mail'));
        $service->sync($admin, $ticket, []);
        $this->assertFalse($notification->shouldSend($observer, 'mail'));
        $service->sync($admin, $ticket, [$observer->id]);
        $observer->update(['company_id' => User::factory()->create()->company_id]);
        $this->assertFalse($notification->shouldSend($observer, 'mail'));
        $this->assertTrue($notification->shouldSend($client, 'mail'));
        $ticket->delete();
        $this->assertFalse($notification->shouldSend($client, 'mail'));
    }

    public function test_rollback_discards_observers_history_and_notifications(): void
    {
        Notification::fake();
        $admin = User::factory()->create(['role' => Role::Admin]);
        $observer = User::factory()->create(['role' => Role::Agent]);
        $ticket = Ticket::factory()->create();
        DB::beginTransaction();
        app(TicketObserverService::class)->sync($admin, $ticket, [$observer->id]);
        Notification::assertNothingSent();
        DB::rollBack();
        $this->assertSame(0, $ticket->observerMemberships()->count());
        $this->assertSame(0, $ticket->events()->count());
        Notification::assertNothingSent();
    }

    public function test_initial_observers_receive_one_creation_notification_after_commit(): void
    {
        Notification::fake();
        $client = User::factory()->create();
        $observer = User::factory()->create(['company_id' => $client->company_id]);
        $category = TicketCategory::factory()->create();
        DB::beginTransaction();
        $ticket = app(TicketService::class)->create($client, new CreateTicketData('Solicitud con observadores', 'Descripción completa', $category->id, observerIds: [$observer->id]));
        Notification::assertNothingSent();
        DB::commit();
        Notification::assertSentTo($observer, TicketUpdated::class);
        Notification::assertSentTimes(TicketUpdated::class, 1);
        $this->assertSame(1, $ticket->events()->count());
        $this->assertSame('created', $ticket->events()->firstOrFail()->event_type);
    }
}
