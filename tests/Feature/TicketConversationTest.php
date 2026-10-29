<?php

namespace Tests\Feature;

use App\Domain\Tickets\Enums\TicketPriority;
use App\Domain\Tickets\Enums\TicketStatus;
use App\Domain\Tickets\Services\TicketService;
use App\Domain\Users\Enums\Role;
use App\Filament\Admin\Resources\Tickets\Pages\ViewTicket;
use App\Models\Ticket;
use App\Models\TicketComment;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class TicketConversationTest extends TestCase
{
    use RefreshDatabase;

    private User $agent;

    private User $client;

    private Ticket $ticket;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        $this->agent = User::factory()->create(['role' => Role::Agent]);
        $this->client = User::factory()->create();
        $this->ticket = Ticket::factory()->create(['company_id' => $this->client->company_id, 'created_by' => $this->client->id]);
    }

    public function test_rich_comments_keep_formatting_and_sanitize_unsafe_content(): void
    {
        $comment = app(TicketService::class)->comment($this->agent, $this->ticket,
            '<p onclick="alert(1)" style="color:red"><strong>Solución</strong> <em>aplicada</em></p><ul><li>Paso uno</li></ul>'
            .'<a href="https://example.test">Referencia</a><a href="javascript:alert(1)">Malicioso</a>'
            .'<script>alert(1)</script><img src="https://example.test/tracker.png" onerror="alert(1)"><iframe src="https://example.test"></iframe>',
            richText: true);
        $this->assertTrue($comment->is_rich_text);
        $this->assertStringContainsString('<strong>Solución</strong>', $comment->body);
        $this->assertStringContainsString('<ul><li>Paso uno</li></ul>', $comment->body);
        $this->assertStringContainsString('href="https://example.test"', $comment->body);
        foreach (['onclick', 'style=', 'javascript:', '<script', '<img', '<iframe'] as $unsafe) {
            $this->assertStringNotContainsString($unsafe, $comment->body);
        }
        $this->assertNotNull($this->ticket->fresh()->first_responded_at);
    }

    public function test_legacy_plain_comments_keep_literal_markup_and_newlines(): void
    {
        $comment = TicketComment::factory()->create(['ticket_id' => $this->ticket->id, 'user_id' => $this->client->id, 'body' => "Literal <strong>text</strong>\nOtra línea"]);
        $this->assertFalse($comment->is_rich_text);
        $html = $comment->renderedBody()->toHtml();
        $this->assertStringContainsString('&lt;strong&gt;text&lt;/strong&gt;', $html);
        $this->assertStringContainsString('<br', $html);
    }

    public function test_sanitizing_is_also_applied_when_rendering_stored_rich_text(): void
    {
        $comment = TicketComment::factory()->create(['ticket_id' => $this->ticket->id, 'body' => '<p>Visible<script>alert(1)</script><img src=x onerror=alert(1)></p>', 'is_rich_text' => true]);
        $this->assertSame('<p>Visible</p>', $comment->renderedBody()->toHtml());
    }

    public function test_empty_rich_text_is_rejected_without_writing_history(): void
    {
        foreach (['<p><br></p>', '<p>&nbsp; &#8203;</p>', '<script>alert(1)</script>'] as $body) {
            try {
                app(TicketService::class)->comment($this->agent, $this->ticket, $body, richText: true);
                $this->fail('Empty rich text was accepted');
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey('body', $exception->errors());
            }
        }
        $this->assertSame(0, $this->ticket->comments()->count());
        $this->assertSame(0, $this->ticket->events()->count());
    }

    public function test_inline_composer_submits_without_navigation_and_resets_only_after_success(): void
    {
        $this->actingAs($this->agent);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        Livewire::test(ViewTicket::class, ['record' => $this->ticket->public_id])
            ->fillForm(['body' => '<p>Respuesta <strong>enriquecida</strong></p>', 'is_internal' => false], 'composerForm')
            ->call('submitComment')->assertHasNoErrors()->assertNoRedirect()
            ->assertDispatched('ticket-comment-posted')->assertSee('Respuesta')->assertSee('Comentario agregado');
        $this->assertDatabaseHas('ticket_comments', ['ticket_id' => $this->ticket->id, 'body' => '<p>Respuesta <strong>enriquecida</strong></p>', 'is_rich_text' => true]);
    }

    public function test_polling_refreshes_all_details_and_preserves_the_composer_draft(): void
    {
        $this->actingAs($this->agent);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $page = Livewire::test(ViewTicket::class, ['record' => $this->ticket->public_id])
            ->fillForm(['body' => '<p>No perder <strong>este borrador</strong></p>', 'is_internal' => true], 'composerForm');
        $draft = $page->get('commentDraft');
        $service = app(TicketService::class);
        $service->changePriority($this->agent, $this->ticket, TicketPriority::Critical);
        $service->transition($this->agent, $this->ticket, TicketStatus::InProgress);
        $service->comment($this->client, $this->ticket, 'Actualización desde otro usuario');
        $page->call('refreshTicket')->assertSee('Crítica')->assertSee('En proceso')->assertSee('Actualización desde otro usuario');
        $this->assertSame($draft, $page->get('commentDraft'));
    }

    public function test_own_actions_immediately_refresh_badges_dates_and_timeline(): void
    {
        $this->actingAs($this->agent);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        Livewire::test(ViewTicket::class, ['record' => $this->ticket->public_id])
            ->callAction('priority', ['priority' => 'critical'])->assertHasNoActionErrors()->assertSee('Crítica')
            ->callAction('transition', ['status' => 'in_progress'])->assertHasNoActionErrors()->assertSee('En proceso')
            ->callAction('transition', ['status' => 'resolved', 'reason' => 'Solución aplicada y verificada'])->assertHasNoActionErrors()
            ->assertSee('Resuelto')->assertSee('Solución aplicada y verificada');
    }

    public function test_private_notes_stay_hidden_after_refresh_and_client_cannot_forge_internal_flag(): void
    {
        $this->actingAs($this->client);
        Filament::setCurrentPanel(Filament::getPanel('client'));
        $page = Livewire::test(\App\Filament\Client\Resources\Tickets\Pages\ViewTicket::class, ['record' => $this->ticket->public_id]);
        app(TicketService::class)->comment($this->agent, $this->ticket, '<p>PRIVATE CONTENT</p>', internal: true, richText: true);
        $page->call('refreshTicket')->assertDontSee('PRIVATE CONTENT')->assertDontSee('Nota interna')
            ->fillForm(['body' => '<p>Comentario cliente</p>'], 'composerForm')->set('commentDraft.is_internal', true)
            ->call('submitComment')->assertHasNoErrors();
        $this->assertDatabaseHas('ticket_comments', ['user_id' => $this->client->id, 'is_internal' => false]);
    }

    public function test_refresh_rechecks_company_visibility_after_access_changes(): void
    {
        $this->actingAs($this->client);
        Filament::setCurrentPanel(Filament::getPanel('client'));
        $page = Livewire::test(\App\Filament\Client\Resources\Tickets\Pages\ViewTicket::class, ['record' => $this->ticket->public_id]);
        $this->ticket->update(['company_id' => User::factory()->create()->company_id]);
        $page->call('refreshTicket')->assertNotFound();
    }

    public function test_ticket_closed_elsewhere_disables_composer_and_rejects_submission(): void
    {
        $this->actingAs($this->agent);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $page = Livewire::test(ViewTicket::class, ['record' => $this->ticket->public_id])
            ->fillForm(['body' => '<p>Borrador pendiente</p>'], 'composerForm');
        $this->ticket->update(['status' => TicketStatus::Closed, 'closed_at' => now()]);
        $page->call('refreshTicket')->assertSee('Cerrado')->assertDontSee('Enviar comentario');
        $page->call('submitComment')->assertForbidden();
        $this->assertSame(0, $this->ticket->comments()->count());
    }
}
