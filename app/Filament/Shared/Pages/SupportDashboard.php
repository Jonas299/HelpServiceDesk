<?php

namespace App\Filament\Shared\Pages;

use App\Domain\Tickets\DTOs\ReportFilters;
use App\Filament\Shared\Schemas\AnalyticsFilters;
use App\Filament\Shared\Widgets\TicketBreakdown;
use App\Filament\Shared\Widgets\TicketDistributionChart;
use App\Filament\Shared\Widgets\TicketOverview;
use App\Filament\Shared\Widgets\TicketTrendChart;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Pages\Dashboard;
use Filament\Pages\Dashboard\Concerns\HasFiltersForm;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\ValidationException;

class SupportDashboard extends Dashboard
{
    use HasFiltersForm { mountHasFilters as protected initializeAnalyticsFilters; }

    protected static ?string $title = 'Panel de control';

    protected static string|\UnitEnum|null $navigationGroup = 'Soporte';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-squares-2x2';

    public ?array $filterDraft = [];

    public function mountHasFilters(): void
    {
        try {
            $this->filters = ReportFilters::fromArray($this->filters ?? [])->values;
        } catch (ValidationException) {
            abort(422, 'El período o los filtros solicitados no son válidos.');
        }

        $this->initializeAnalyticsFilters();
    }

    public function applyFilters(): void
    {
        $state = $this->getFiltersForm()->getState();

        try {
            $this->filters = ReportFilters::fromArray($state)->values;
        } catch (ValidationException $exception) {
            throw ValidationException::withMessages(
                collect($exception->errors())
                    ->mapWithKeys(fn ($messages, $field) => ['filterDraft.'.$field => $messages])
                    ->all()
            );
        }

        $this->afterApplyingFilters();
    }

    protected function afterApplyingFilters(): void {}

    public function resetFilters(): void
    {
        $this->getFiltersForm()->fill(ReportFilters::fromArray()->values);
        $this->applyFilters();
        $this->resetValidation();
    }

    public function persistsFiltersInSession(): bool
    {
        return false;
    }

    public function getSubheading(): ?string
    {
        return 'Una vista clara de tus solicitudes, prioridades y resultados.';
    }

    public function getHeader(): ?View
    {
        return view('filament.dashboard.header');
    }

    public function filtersForm(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Período de análisis')
                ->icon('heroicon-o-adjustments-horizontal')
                ->description(fn () => 'Del '.CarbonImmutable::parse($this->filters['from'])->format('d/m/Y')
                    .' al '.CarbonImmutable::parse($this->filters['until'])->format('d/m/Y')
                    .' · Según fecha de creación')
                ->schema(AnalyticsFilters::fields(auth()->user()))
                ->columnSpanFull()
                ->columns(['md' => 3])
                ->collapsible()
                ->collapsed(),
        ])->statePath('filterDraft');
    }

    public function getWidgets(): array
    {
        return [
            TicketOverview::class,
            TicketTrendChart::class,
            TicketDistributionChart::make(['dimension' => 'status']),
            TicketDistributionChart::make(['dimension' => 'priority']),
            TicketBreakdown::class,
        ];
    }

    protected function getHeaderActions(): array
    {
        $path = Filament::getCurrentPanel()->getPath();

        return [
            Action::make('createTicket')
                ->label('Crear ticket')
                ->icon('heroicon-o-plus')
                ->url(url($path.'/tickets/create')),
        ];
    }
}
