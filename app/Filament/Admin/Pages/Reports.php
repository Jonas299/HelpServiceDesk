<?php

namespace App\Filament\Admin\Pages;

use App\Domain\Tickets\DTOs\ReportFilters;
use App\Domain\Tickets\Services\TicketAnalyticsService;
use App\Domain\Tickets\Services\TicketReportService;
use App\Filament\Shared\Pages\SupportDashboard;
use App\Filament\Shared\Resources\BaseTicketResource;
use App\Filament\Shared\Schemas\AnalyticsFilters;
use App\Filament\Shared\Widgets\ResponseMetrics;
use App\Filament\Shared\Widgets\TicketDistributionChart;
use App\Filament\Shared\Widgets\TicketOverview;
use App\Filament\Shared\Widgets\TicketTrendChart;
use Filament\Actions\Action;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\StreamedResponse;

class Reports extends SupportDashboard implements HasTable
{
    use InteractsWithTable;

    protected static string $routePath = '/reports';

    protected static ?string $title = 'Reportes';

    protected static string|\UnitEnum|null $navigationGroup = 'Análisis';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-chart-bar-square';

    protected static ?int $navigationSort = 1;

    protected string $view = 'filament.reports.index';

    public static function canAccess(): bool
    {
        return Gate::allows('viewReports');
    }

    public function getSubheading(): ?string
    {
        return 'Analiza la operación y descarga los datos que necesitas.';
    }

    public function getHeader(): ?View
    {
        return null;
    }

    public function filtersForm(Schema $schema): Schema
    {
        return $schema->components([Section::make('Filtros del reporte')->icon('heroicon-o-funnel')->description('Gráficos, indicadores, listado y CSV comparten estos filtros. Hasta 366 días.')->schema(AnalyticsFilters::fields(auth()->user(), true))->columnSpanFull()->columns(['md' => 3, 'xl' => 4])])->statePath('filterDraft');
    }

    public function getWidgets(): array
    {
        return [TicketOverview::class, ResponseMetrics::class, TicketTrendChart::class, ...array_map(fn ($dimension) => TicketDistributionChart::make(['dimension' => $dimension]), ['status', 'priority', 'company', 'agent', 'category'])];
    }

    public function table(Table $table): Table
    {
        return BaseTicketResource::table($table)->query(fn () => app(TicketAnalyticsService::class)->query(auth()->user(), ReportFilters::fromArray($this->filters ?? [])))->filters([])->recordActions([])->recordUrl(fn ($record) => url('admin/tickets/'.$record->public_id))->heading('Detalle de tickets')->description('Los mismos datos del reporte, con búsqueda y paginación.');
    }

    protected function afterApplyingFilters(): void
    {
        $this->resetPage();
    }

    protected function getHeaderActions(): array
    {
        return [Action::make('export')->label('Descargar CSV')->icon('heroicon-o-arrow-down-tray')->action(fn () => $this->export())];
    }

    public function export(): StreamedResponse
    {
        Gate::authorize('viewReports');

        return app(TicketReportService::class)->download(auth()->user(), ReportFilters::fromArray($this->filters ?? []));
    }
}
