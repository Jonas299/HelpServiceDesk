<?php

namespace Tests\Feature;

use App\Domain\Tickets\Enums\TicketPriority;
use App\Domain\Tickets\Services\PriorityGuidelineService;
use App\Domain\Users\Enums\Role;
use App\Filament\Admin\Resources\PriorityGuidelines\Pages\ManagePriorityGuidelines;
use App\Filament\Client\Resources\Tickets\Pages\CreateTicket;
use App\Models\PriorityGuideline;
use App\Models\User;
use Filament\Actions\EditAction;
use Filament\Facades\Filament;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class PriorityGuidelinesTest extends TestCase
{
    use RefreshDatabase;

    public function test_all_priorities_have_criteria_but_no_initial_time_commitments(): void
    {
        $this->assertSame(4, PriorityGuideline::count());
        foreach (TicketPriority::cases() as $priority) {
            $guide = app(PriorityGuidelineService::class)->describe($priority);
            $this->assertNotEmpty($guide['criteria']);
            $this->assertFalse($guide['configured']);
            $this->assertSame('Sin definir', $guide['response']);
            $this->assertSame('Sin definir', $guide['resolution']);
        }
    }

    public function test_admin_can_configure_targets_and_service_formats_minutes_consistently(): void
    {
        $admin = User::factory()->create(['role' => Role::Admin]);
        $record = PriorityGuideline::where('priority', 'critical')->firstOrFail();
        $service = app(PriorityGuidelineService::class);
        $service->save($admin, $record, ['criteria' => 'Servicio esencial sin alternativa.', 'response_minutes' => 45, 'resolution_minutes' => 360, 'calendar' => 'continuous']);
        $guide = $service->describe(TicketPriority::Critical);
        $this->assertTrue($guide['configured']);
        $this->assertSame('45 min', $guide['response']);
        $this->assertSame('6 h', $guide['resolution']);
        $this->assertSame('1 h 30 min', $service->duration(90));
    }

    public function test_partial_or_inconsistent_targets_are_rejected_without_changing_the_record(): void
    {
        $admin = User::factory()->create(['role' => Role::Admin]);
        $record = PriorityGuideline::where('priority', 'high')->firstOrFail();
        $service = app(PriorityGuidelineService::class);
        foreach ([['response_minutes' => 30], ['response_minutes' => -1, 'resolution_minutes' => 60, 'calendar' => 'continuous'], ['response_minutes' => 60, 'resolution_minutes' => 30, 'calendar' => 'continuous'], ['response_minutes' => 30, 'resolution_minutes' => 60, 'calendar' => 'business']] as $invalid) {
            try {
                $service->save($admin, $record, ['criteria' => $record->criteria, ...$invalid]);
                $this->fail('Invalid priority targets accepted');
            } catch (ValidationException $exception) {
                $this->assertNotEmpty($exception->errors());
            }
        }
        $this->assertNull($record->fresh()->response_minutes);
    }

    public function test_targets_can_be_cleared_and_priority_cannot_be_changed_by_payload(): void
    {
        $admin = User::factory()->create(['role' => Role::Admin]);
        $record = PriorityGuideline::where('priority', 'low')->firstOrFail();
        $service = app(PriorityGuidelineService::class);
        $service->save($admin, $record, ['criteria' => $record->criteria, 'priority' => 'critical', 'response_minutes' => 60, 'resolution_minutes' => 120, 'calendar' => 'continuous']);
        $this->assertSame(TicketPriority::Low, $record->fresh()->priority);
        $service->save($admin, $record, ['criteria' => $record->criteria, 'response_minutes' => '', 'resolution_minutes' => '', 'calendar' => '']);
        $this->assertFalse($record->fresh()->hasTargets());
    }

    public function test_non_admin_cannot_configure_priority_guidelines(): void
    {
        $agent = User::factory()->create(['role' => Role::Agent]);
        $this->actingAs($agent)->get('/admin/priority-guidelines')->assertForbidden();
        $this->expectException(AuthorizationException::class);
        app(PriorityGuidelineService::class)->save($agent, PriorityGuideline::firstOrFail(), ['criteria' => 'Cambio no autorizado']);
    }

    public function test_admin_can_edit_guidelines_from_filament(): void
    {
        $admin = User::factory()->create(['role' => Role::Admin]);
        $record = PriorityGuideline::where('priority', 'medium')->firstOrFail();
        $this->actingAs($admin);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        Livewire::test(ManagePriorityGuidelines::class)->callTableAction(EditAction::class, $record, [
            'criteria' => 'Afectación parcial con alternativa temporal.',
            'response_minutes' => 90,
            'resolution_minutes' => 480,
            'calendar' => 'continuous',
        ])->assertHasNoTableActionErrors();
        $this->assertSame(90, $record->fresh()->response_minutes);
        $this->assertSame(480, $record->fresh()->resolution_minutes);
    }

    public function test_creation_form_displays_guidelines_for_selected_priority(): void
    {
        $client = User::factory()->create();
        $this->actingAs($client);
        Filament::setCurrentPanel(Filament::getPanel('client'));
        Livewire::test(CreateTicket::class)
            ->fillForm(['priority' => 'critical'])->assertSee('Interrupción general')->assertSee('Sin definir');
    }
}
