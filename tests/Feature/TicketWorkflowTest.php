<?php

namespace Tests\Feature;

use App\Domain\Configuration\Services\ConfigurationService;
use App\Domain\Tickets\Contracts\TicketRepositoryInterface;
use App\Domain\Tickets\DTOs\CreateTicketData;
use App\Domain\Tickets\Enums\TicketPriority;
use App\Domain\Tickets\Enums\TicketStatus;
use App\Domain\Tickets\Services\AttachmentService;
use App\Domain\Tickets\Services\TicketService;
use App\Domain\Users\Enums\Role;
use App\Filament\Admin\Resources\Companies\Pages\ManageCompanies;
use App\Filament\Admin\Resources\Tickets\Pages\ViewTicket;
use App\Filament\Client\Resources\Tickets\Pages\CreateTicket;
use App\Filament\Client\Resources\Tickets\Pages\ListTickets;
use App\Models\Company;
use App\Models\Ticket;
use App\Models\TicketAttachment;
use App\Models\TicketCategory;
use App\Models\TicketComment;
use App\Models\TicketEvent;
use App\Models\User;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\RestoreAction;
use Filament\Auth\Pages\Login;
use Filament\Facades\Filament;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class TicketWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $agent;

    private User $client;

    private User $other;

    private TicketCategory $category;

    private TicketService $service;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        $this->assertSame('helpdesk_testing', DB::connection()->getDatabaseName(), 'Las pruebas deben usar su base exclusiva.');
        $this->admin = User::factory()->create(['role' => Role::Admin, 'company_id' => null]);
        $this->agent = User::factory()->create(['role' => Role::Agent, 'company_id' => null]);
        $this->client = User::factory()->create();
        $this->other = User::factory()->create();
        $this->category = TicketCategory::factory()->create();
        $this->service = app(TicketService::class);
    }

    private function ticket(?User $client = null): Ticket
    {
        return $this->service->create($client ?? $this->client, new CreateTicketData('Problema de red', 'No hay acceso a la red empresarial.', $this->category->id, TicketPriority::High, $this->other->company_id));
    }

    public function test_client_company_is_derived_from_authenticated_user_and_numbers_are_unique(): void
    {
        $a = $this->ticket();
        $b = $this->ticket();
        $this->assertSame($this->client->company_id, $a->company_id);
        $this->assertNotSame($a->ticket_number, $b->ticket_number);
        $this->assertMatchesRegularExpression('/^TK-\d{4}-\d{6}$/', $a->ticket_number);
        $this->assertSame(26, strlen($a->public_id));
        $this->assertSame('created', $a->events->first()->event_type);
    }

    public function test_complete_lifecycle_reassignment_reopening_and_timestamps(): void
    {
        $t = $this->ticket();
        $t = $this->service->assign($this->admin, $t, $this->agent->id);
        $this->assertSame(TicketStatus::Assigned, $t->status);
        $second = User::factory()->create(['role' => Role::Agent]);
        $t = $this->service->assign($this->admin, $t, $second->id);
        $t = $this->service->transition($this->agent, $t, TicketStatus::InProgress);
        $t = $this->service->transition($this->agent, $t, TicketStatus::WaitingCustomer);
        $t = $this->service->transition($this->agent, $t, TicketStatus::InProgress);
        $t = $this->service->transition($this->agent, $t, TicketStatus::Resolved, 'Se restauró la conectividad.');
        $this->assertNotNull($t->resolved_at);
        $t = $this->service->transition($this->agent, $t, TicketStatus::InProgress, 'El problema sigue ocurriendo.');
        $this->assertNull($t->resolved_at);
        $t = $this->service->transition($this->agent, $t, TicketStatus::Resolved, 'Se sustituyó el equipo averiado.');
        $t = $this->service->transition($this->admin, $t, TicketStatus::Closed);
        $this->assertNotNull($t->closed_at);
        $this->assertEqualsCanonicalizing(['created', 'assigned', 'reassigned', 'status_changed', 'status_changed', 'status_changed', 'resolved', 'reopened', 'resolved', 'closed'], $t->events()->pluck('event_type')->all());
    }

    public function test_invalid_transition_does_not_write_audit_or_status(): void
    {
        $t = $this->ticket();
        try {
            $this->service->transition($this->agent, $t, TicketStatus::Closed);
            $this->fail('Transición aceptada');
        } catch (ValidationException) {
        }
        $this->assertSame(TicketStatus::New, $t->fresh()->status);
        $this->assertSame(1, $t->events()->count());
    }

    public function test_resolution_and_cancellation_require_justification(): void
    {
        foreach ([TicketStatus::Resolved, TicketStatus::Cancelled] as $status) {
            $t = $this->ticket();
            if ($status === TicketStatus::Resolved) {
                $t = $this->service->transition($this->agent, $t, TicketStatus::InProgress);
            } try {
                $this->service->transition($this->agent, $t, $status);
                $this->fail('Falta justificación');
            } catch (ValidationException $e) {
                $this->assertArrayHasKey('reason', $e->errors());
            }
        }
    }

    public function test_client_cannot_change_priority_or_assign(): void
    {
        $t = $this->ticket();
        foreach (['priority', 'assign', 'status'] as $action) {
            try {
                match ($action) {
                    'priority' => $this->service->changePriority($this->client, $t, TicketPriority::Critical),'assign' => $this->service->assign($this->client, $t, $this->agent->id),'status' => $this->service->transition($this->client, $t, TicketStatus::InProgress)
                };
                $this->fail('Operación sin autorización');
            } catch (AuthorizationException) {
            }
        } $this->assertSame(1, $t->events()->count());
    }

    public function test_internal_notes_are_private_and_first_response_ignores_internal_notes(): void
    {
        $t = $this->ticket();
        $note = $this->service->comment($this->agent, $t, 'Secreto interno', true);
        $this->assertNull($t->fresh()->first_responded_at);
        $this->service->comment($this->agent, $t, 'Estamos investigando.');
        $this->assertNotNull($t->fresh()->first_responded_at);
        $this->assertFalse(Gate::forUser($this->client)->allows('view', $note));
        $this->assertSame(1, TicketComment::query()->visibleTo($this->client)->count());
        try {
            $this->service->comment($this->client, $t, 'Nota manipulada', true);
            $this->fail('Nota interna aceptada');
        } catch (AuthorizationException) {
        }
    }

    public function test_company_isolation_blocks_view_comment_edit_and_attachment_download(): void
    {
        $t = $this->ticket();
        $this->assertFalse(Gate::forUser($this->other)->allows('view', $t));
        $this->assertFalse(Gate::forUser($this->other)->allows('update', $t));
        $this->assertSame(0, app(TicketRepositoryInterface::class)->visibleTo($this->other)->count());
        try {
            $this->service->comment($this->other, $t, 'Intrusión');
            $this->fail('Comentario entre empresas aceptado');
        } catch (AuthorizationException) {
        }
        Storage::fake('local');
        $a = app(AttachmentService::class)->upload($this->client, $t, UploadedFile::fake()->createWithContent('readme.txt', 'Archivo de prueba'));
        $this->actingAs($this->other)->get('/attachments/'.$a->public_id)->assertNotFound();
        $this->actingAs($this->client)->get('/attachments/'.$a->public_id)->assertOk()->assertDownload('readme.txt');
    }

    public function test_upload_rejects_executable_files_and_records_valid_files(): void
    {
        Storage::fake('local');
        $t = $this->ticket();
        try {
            app(AttachmentService::class)->upload($this->client, $t, UploadedFile::fake()->createWithContent('script.php', '<?php echo 1;'));
            $this->fail('Ejecutable aceptado');
        } catch (ValidationException) {
        }
        $a = app(AttachmentService::class)->upload($this->client, $t, UploadedFile::fake()->createWithContent('info.txt', 'Datos de diagnóstico'));
        Storage::disk('local')->assertExists($a->path);
        $this->assertSame(1, $t->events()->where('event_type', 'attached')->count());
    }

    public function test_soft_deletes_preserve_tickets_and_audit(): void
    {
        $t = $this->ticket();
        $t->delete();
        $this->assertSoftDeleted($t);
        $this->assertSame(1, Ticket::withTrashed()->count());
        $this->assertSame(1, TicketEvent::count());
        $next = $this->ticket();
        $this->assertNotSame($t->ticket_number, $next->ticket_number);
    }

    public function test_audit_cannot_be_changed_via_model(): void
    {
        $event = $this->ticket()->events()->first();
        $this->expectException(\LogicException::class);
        $event->update(['event_type' => 'forged']);
    }

    public function test_audit_cannot_be_changed_via_sql(): void
    {
        $event = $this->ticket()->events()->first();
        $this->expectException(QueryException::class);
        DB::table('ticket_events')->where('id', $event->id)->delete();
    }

    public function test_priority_changes_and_filters_are_audited(): void
    {
        $t = $this->ticket();
        $this->service->changePriority($this->agent, $t, TicketPriority::Critical);
        $repo = app(TicketRepositoryInterface::class);
        $this->assertSame(1, $repo->visibleTo($this->agent, ['priority' => 'critical', 'company_id' => $this->client->company_id, 'category_id' => $this->category->id, 'status' => 'new', 'from' => now('America/Santo_Domingo')->toDateString(), 'until' => now('America/Santo_Domingo')->toDateString()])->count());
        $this->assertSame(0, $repo->visibleTo($this->agent, ['priority' => 'low'])->count());
        $this->assertSame(1, $t->events()->where('event_type', 'priority_changed')->count());
    }

    public function test_inactive_company_cannot_create_tickets(): void
    {
        $this->client->company->update(['active' => false]);
        $this->client->unsetRelation('company');
        $this->expectException(AuthorizationException::class);
        $this->ticket();
    }

    public function test_panels_enforce_roles_and_no_public_registration(): void
    {
        $this->get('/admin')->assertRedirect('/admin/login');
        $this->get('/portal')->assertRedirect('/portal/login');
        $this->get('/portal/register')->assertNotFound();
        $this->actingAs($this->client)->get('/admin')->assertForbidden();
        $this->actingAs($this->agent)->get('/portal')->assertForbidden();
        $this->actingAs($this->admin)->get('/admin')->assertOk();
        $this->actingAs($this->client)->get('/portal')->assertOk();
    }

    public function test_client_urls_hide_other_company_tickets_and_internal_notes(): void
    {
        $t = $this->ticket();
        $this->service->comment($this->agent, $t, 'INTERNAL SECRET', true);
        $this->service->comment($this->agent, $t, 'Mensaje público');
        $this->actingAs($this->other)->get('/portal/tickets/'.$t->public_id)->assertNotFound();
        $this->actingAs($this->client)->get('/portal/tickets/'.$t->public_id)->assertOk()->assertSee('Mensaje público')->assertDontSee('INTERNAL SECRET')->assertDontSee('Nota interna');
        $this->get('/portal/tickets/'.$t->id)->assertNotFound();
    }

    public function test_portal_table_scopes_records_and_filters(): void
    {
        $a = $this->ticket();
        $b = $this->ticket($this->other);
        $this->actingAs($this->client);
        Filament::setCurrentPanel(Filament::getPanel('client'));
        Livewire::test(ListTickets::class)->assertCanSeeTableRecords([$a])->assertCanNotSeeTableRecords([$b])->filterTable('priority', 'low')->assertCanNotSeeTableRecords([$a]);
    }

    public function test_portal_creation_uses_service_and_ignores_foreign_company(): void
    {
        $this->actingAs($this->client);
        Filament::setCurrentPanel(Filament::getPanel('client'));
        Livewire::test(CreateTicket::class)->fillForm(['subject' => 'Nuevo problema', 'description' => 'Descripción detallada de la incidencia', 'category_id' => $this->category->id, 'priority' => 'high'])->call('create')->assertHasNoFormErrors();
        $this->assertDatabaseHas('tickets', ['company_id' => $this->client->company_id, 'subject' => 'Nuevo problema']);
        $this->assertSame(1, TicketEvent::count());
    }

    public function test_admin_configuration_and_agent_restrictions(): void
    {
        $this->actingAs($this->agent)->get('/admin/companies')->assertForbidden();
        $this->get('/admin/users')->assertForbidden();
        $this->actingAs($this->admin)->get('/admin/companies')->assertOk();
        $this->get('/admin/users')->assertOk();
        $this->get('/admin/categories')->assertOk();
        $company = app(ConfigurationService::class)->save($this->admin, Company::class, ['name' => 'Empresa creada', 'active' => true]);
        $this->assertTrue($company->exists);
    }

    public function test_client_user_requires_company_and_password_is_hashed(): void
    {
        $service = app(ConfigurationService::class);
        try {
            $service->save($this->admin, User::class, ['name' => 'Cliente nuevo', 'email' => 'new@example.test', 'password' => 'long-password-123', 'role' => 'client', 'active' => true]);
            $this->fail('Cliente sin empresa');
        } catch (ValidationException) {
        }
        $user = $service->save($this->admin, User::class, ['name' => 'Cliente nuevo', 'email' => 'new@example.test', 'password' => 'long-password-123', 'role' => 'client', 'company_id' => $this->client->company_id, 'active' => true]);
        $this->assertTrue(Hash::check('long-password-123', $user->password));
    }

    public function test_login_accepts_correct_credentials_and_rejects_invalid_password(): void
    {
        $this->admin->update(['password' => 'testing-password-123']);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        Livewire::test(Login::class)
            ->fillForm(['email' => $this->admin->email, 'password' => 'wrong-password'])
            ->call('authenticate')->assertHasFormErrors();
        $this->assertGuest();
        Livewire::test(Login::class)
            ->fillForm(['email' => $this->admin->email, 'password' => 'testing-password-123'])
            ->call('authenticate')->assertHasNoFormErrors();
        $this->assertAuthenticatedAs($this->admin);
    }

    public function test_livewire_actions_use_domain_services(): void
    {
        $ticket = $this->ticket();
        $this->actingAs($this->agent);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        Livewire::test(ViewTicket::class, ['record' => $ticket->public_id])
            ->callAction('assign', ['assigned_to' => $this->agent->id])
            ->assertHasNoActionErrors()
            ->callAction('priority', ['priority' => 'critical'])
            ->assertHasNoActionErrors()
            ->callAction('transition', ['status' => 'in_progress'])
            ->assertHasNoActionErrors()
            ->fillForm(['body' => '<p>Respuesta desde la interfaz</p>', 'is_internal' => false], 'composerForm')
            ->call('submitComment')->assertHasNoErrors();
        $this->assertSame(TicketStatus::InProgress, $ticket->fresh()->status);
        $this->assertSame(TicketPriority::Critical, $ticket->fresh()->priority);
        $this->assertDatabaseHas('ticket_comments', ['ticket_id' => $ticket->id, 'body' => '<p>Respuesta desde la interfaz</p>', 'is_rich_text' => true]);
    }

    public function test_attachment_requires_authentication(): void
    {
        $attachment = TicketAttachment::factory()->create();
        $this->get('/attachments/'.$attachment->public_id)->assertRedirect('/portal/login');
    }

    public function test_deleted_or_disabled_users_lose_panel_access(): void
    {
        $this->client->update(['active' => false]);
        $this->actingAs($this->client)->get('/portal')->assertForbidden();
        $this->admin->delete();
        $this->assertFalse($this->admin->canAccessPanel(Filament::getPanel('admin')));
    }

    public function test_admin_can_create_edit_archive_and_restore_company_from_filament(): void
    {
        $this->actingAs($this->admin);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $page = Livewire::test(ManageCompanies::class)
            ->callAction(CreateAction::class, ['name' => 'Nueva empresa de interfaz', 'active' => true])
            ->assertHasNoActionErrors();
        $company = Company::where('name', 'Nueva empresa de interfaz')->firstOrFail();
        $page->callTableAction(EditAction::class, $company, ['name' => 'Empresa actualizada', 'active' => true])->assertHasNoTableActionErrors();
        $this->assertSame('Empresa actualizada', $company->fresh()->name);
        $page->callTableAction(DeleteAction::class, $company)->assertHasNoTableActionErrors();
        $this->assertSoftDeleted($company);
        $page->filterTable('trashed', 'only')->callTableAction(RestoreAction::class, $company)->assertHasNoTableActionErrors();
        $this->assertNotSoftDeleted($company);
    }
}
