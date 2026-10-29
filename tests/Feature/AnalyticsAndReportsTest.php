<?php

namespace Tests\Feature;

use App\Domain\Tickets\DTOs\ReportFilters;
use App\Domain\Tickets\Enums\TicketPriority;
use App\Domain\Tickets\Enums\TicketStatus;
use App\Domain\Tickets\Services\TicketAnalyticsService;
use App\Domain\Tickets\Services\TicketReportService;
use App\Domain\Users\Enums\Role;
use App\Filament\Admin\Pages\Reports;
use App\Filament\Admin\Resources\Tickets\Pages\ViewTicket;
use App\Filament\Shared\Widgets\TicketDistributionChart;
use App\Filament\Shared\Widgets\TicketTrendChart;
use App\Models\Ticket;
use App\Models\TicketCategory;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class AnalyticsAndReportsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $client;

    private User $other;

    private TicketCategory $category;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setDate(2026, 10, 25)->setTime(16, 0));
        $this->admin = User::factory()->create(['role' => Role::Admin]);
        $this->client = User::factory()->create();
        $this->other = User::factory()->create();
        $this->category = TicketCategory::factory()->create();
    }

    private function ticket(User $client, array $attributes = []): Ticket
    {
        return Ticket::factory()->create(array_merge([
            'company_id' => $client->company_id,
            'created_by' => $client->id,
            'category_id' => $this->category->id,
            'created_at' => '2026-10-25 14:00:00+00',
        ], $attributes));
    }

    private function filters(array $overrides = []): ReportFilters
    {
        return ReportFilters::fromArray(array_merge(['from' => '2026-10-01', 'until' => '2026-10-25'], $overrides));
    }

    public function test_metrics_respect_company_scope_even_with_manipulated_filters(): void
    {
        $this->ticket($this->client, ['priority' => TicketPriority::Critical]);
        $this->ticket($this->other, ['status' => TicketStatus::Resolved, 'resolved_at' => now()]);
        $analytics = app(TicketAnalyticsService::class);
        $summary = $analytics->summary($this->client, $this->filters());
        $this->assertSame(1, $summary['total']);
        $this->assertSame(1, $summary['critical']);
        $this->assertSame(0, $summary['completed']);
        $this->assertSame(0, $analytics->summary($this->client, $this->filters(['company_id' => $this->other->company_id]))['total']);
        $this->assertSame(2, $analytics->summary($this->admin, $this->filters())['total']);
        foreach (['status', 'priority', 'company', 'agent', 'category'] as $dimension) {
            $data = $analytics->distribution($this->client, $this->filters(), $dimension);
            $this->assertSame(1, array_sum($data['values']));
        }
    }

    public function test_metrics_use_real_response_and_resolution_times(): void
    {
        $this->ticket($this->client, ['status' => TicketStatus::Resolved, 'first_responded_at' => '2026-10-25 16:00:00+00', 'resolved_at' => '2026-10-25 20:00:00+00']);
        $data = app(TicketAnalyticsService::class)->summary($this->admin, $this->filters());
        $this->assertSame(2.0, $data['response_hours']);
        $this->assertSame(6.0, $data['resolution_hours']);
        $this->assertSame(100.0, $data['completion_rate']);
        $this->assertSame(0, $data['open']);
    }

    public function test_daily_trend_fills_empty_days_and_uses_local_date_boundaries(): void
    {
        $this->ticket($this->client, ['created_at' => '2026-10-25 03:59:59+00']); // 24 October locally.
        $this->ticket($this->client, ['created_at' => '2026-10-25 04:00:00+00']); // 25 October locally.
        $this->ticket($this->client, ['created_at' => '2026-10-26 03:59:59+00']); // Still 25 October.
        $this->ticket($this->client, ['created_at' => '2026-10-26 04:00:00+00']); // Outside period.
        $analytics = app(TicketAnalyticsService::class);
        $trend = $analytics->trend($this->client, $this->filters(['from' => '2026-10-23']));
        $this->assertSame(['23/10', '24/10', '25/10'], $trend['labels']);
        $this->assertSame([0, 1, 2], $trend['values']);
        $this->assertSame(2, $analytics->summary($this->client, $this->filters(['from' => '2026-10-25']))['total']);
    }

    public function test_report_filters_apply_to_all_aggregates_and_exclude_archived_tickets(): void
    {
        $agent = User::factory()->create(['role' => Role::Agent]);
        $this->ticket($this->client, ['assigned_to' => $agent->id, 'priority' => TicketPriority::Critical]);
        $this->ticket($this->client, ['priority' => TicketPriority::Low]);
        $this->ticket($this->client, ['priority' => TicketPriority::Critical])->delete();
        $filters = $this->filters(['company_id' => $this->client->company_id, 'assigned_to' => $agent->id, 'category_id' => $this->category->id, 'priority' => 'critical', 'status' => 'new']);
        $analytics = app(TicketAnalyticsService::class);
        $this->assertSame(1, $analytics->summary($this->admin, $filters)['total']);
        $this->assertSame(1, array_sum($analytics->trend($this->admin, $filters)['values']));
        $this->assertSame(1, array_sum($analytics->distribution($this->admin, $filters, 'agent')['values']));
    }

    public function test_empty_period_has_zero_counts_and_no_fabricated_durations(): void
    {
        $data = app(TicketAnalyticsService::class)->summary($this->client, $this->filters());
        $this->assertSame(0, $data['total']);
        $this->assertSame(0, $data['completion_rate']);
        $this->assertNull($data['response_hours']);
        $this->assertNull($data['resolution_hours']);
    }

    public function test_invalid_date_ranges_and_oversized_periods_are_rejected(): void
    {
        foreach ([['from' => 'invalid'], ['from' => '2026-10-26'], ['from' => '2024-01-01']] as $overrides) {
            try {
                $this->filters($overrides);
                $this->fail('Invalid report range accepted');
            } catch (ValidationException $exception) {
                $this->assertNotEmpty($exception->errors());
            }
        }
    }

    public function test_reports_are_only_available_to_active_internal_users(): void
    {
        $this->get('/admin/reports')->assertRedirect('/admin/login');
        $this->actingAs($this->client)->get('/admin/reports')->assertForbidden();
        $this->get('/portal/reports')->assertNotFound();
        $this->actingAs($this->admin)->get('/admin/reports')->assertOk()->assertSee('Filtros del reporte')->assertSee('Descargar CSV');
        $agent = User::factory()->create(['role' => Role::Agent]);
        $this->actingAs($agent)->get('/admin/reports')->assertOk();
        $agent->update(['active' => false]);
        $this->get('/admin/reports')->assertForbidden();
    }

    public function test_csv_export_matches_filters_and_neutralizes_spreadsheet_formulas(): void
    {
        $this->ticket($this->client, ['subject' => '=HYPERLINK("https://example.test")', 'priority' => TicketPriority::Critical]);
        $this->ticket($this->other, ['subject' => 'Must not appear']);
        $response = app(TicketReportService::class)->download($this->admin, $this->filters(['company_id' => $this->client->company_id]));
        ob_start();
        $response->sendContent();
        $csv = ob_get_clean();
        $this->assertStringContainsString("'=HYPERLINK", $csv);
        $this->assertStringNotContainsString('Must not appear', $csv);
        $this->assertStringContainsString('Crítica', $csv);
        $this->assertStringContainsString('25/10/2026 10:00', $csv);
        $this->assertStringContainsString('tickets-2026-10-01-2026-10-25.csv', $response->headers->get('Content-Disposition'));
    }

    public function test_client_cannot_call_the_export_service_directly(): void
    {
        $this->expectException(AuthorizationException::class);
        app(TicketReportService::class)->download($this->client, $this->filters());
    }

    public function test_livewire_report_filters_refresh_table_and_export(): void
    {
        $a = $this->ticket($this->client, ['priority' => TicketPriority::Critical]);
        $b = $this->ticket($this->other, ['priority' => TicketPriority::Low]);
        $this->actingAs($this->admin);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        Livewire::test(Reports::class)->assertCanSeeTableRecords([$a, $b])
            ->set('filterDraft.company_id', $this->client->company_id)->call('applyFilters')
            ->assertCanSeeTableRecords([$a])->assertCanNotSeeTableRecords([$b])
            ->call('export')->assertFileDownloaded('tickets-2026-09-26-2026-10-25.csv');
    }

    public function test_charts_render_real_data_for_the_authenticated_company(): void
    {
        $this->ticket($this->client);
        $this->ticket($this->other);
        $this->actingAs($this->client);
        Filament::setCurrentPanel(Filament::getPanel('client'));
        Livewire::withoutLazyLoading()->test(TicketTrendChart::class, ['pageFilters' => $this->filters()->values])
            ->assertSee('Actividad de soporte')->assertSet('dataChecksum', fn ($value) => filled($value));
        foreach (['status', 'priority', 'category', 'agent', 'company'] as $dimension) {
            Livewire::withoutLazyLoading()->test(TicketDistributionChart::class, ['pageFilters' => $this->filters()->values, 'dimension' => $dimension])
                ->assertSet('dataChecksum', fn ($value) => filled($value));
        }
    }

    public function test_timeline_renders_human_labels_instead_of_raw_json(): void
    {
        $ticket = $this->ticket($this->client);
        $ticket->events()->create(['actor_id' => $this->admin->id, 'event_type' => 'status_changed', 'old_values' => ['status' => 'new'], 'new_values' => ['status' => 'in_progress']]);
        $this->actingAs($this->client)->get('/portal/tickets/'.$ticket->public_id)
            ->assertOk()->assertSee('Historial de actividad')->assertSee('desk-timeline')
            ->assertSee('Nuevo')->assertSee('En proceso')->assertDontSee('in_progress');
    }

    public function test_invalid_draft_does_not_replace_applied_filters(): void
    {
        $this->actingAs($this->admin);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        Livewire::test(Reports::class)
            ->set('filterDraft.from', '2026-11-01')
            ->call('applyFilters')->assertHasErrors(['filterDraft.until'])
            ->assertSet('filters.from', '2026-09-26')
            ->call('resetFilters')->assertHasNoErrors()
            ->assertSet('filterDraft.from', '2026-09-26 00:00:00');
    }

    public function test_manipulated_initial_report_dates_return_validation_error(): void
    {
        $this->actingAs($this->admin)->get('/admin/reports?'.http_build_query(['filters' => ['from' => 'invalid']]))->assertStatus(422);
    }

    public function test_terminal_tickets_hide_unavailable_workflow_actions(): void
    {
        $ticket = $this->ticket($this->client, ['status' => TicketStatus::Closed]);
        $this->actingAs($this->admin);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        Livewire::test(ViewTicket::class, ['record' => $ticket->public_id])
            ->assertActionHidden('assign')->assertActionHidden('transition')->assertActionDoesNotExist('comment')->assertDontSee('Enviar comentario')->assertActionHidden('attach');
    }

    public function test_ticket_description_is_escaped_while_preserving_line_breaks(): void
    {
        $ticket = $this->ticket($this->client, ['description' => "<script>alert(1)</script>\nSegunda línea"]);
        $this->actingAs($this->client)->get('/portal/tickets/'.$ticket->public_id)->assertOk()
            ->assertSee('&lt;script&gt;', false)->assertDontSee('<script>alert(1)</script>', false)
            ->assertSee('Segunda línea');
    }
}
