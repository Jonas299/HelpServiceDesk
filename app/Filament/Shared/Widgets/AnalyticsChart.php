<?php

namespace App\Filament\Shared\Widgets;

use App\Filament\Shared\Widgets\Concerns\UsesTicketAnalytics;
use Filament\Widgets\ChartWidget;

abstract class AnalyticsChart extends ChartWidget
{
    use UsesTicketAnalytics;

    protected ?string $emptyStateHeading = 'Sin actividad en este período';

    protected ?string $emptyStateDescription = 'Prueba otro período o ajusta los filtros para ver resultados.';

    protected string|\BackedEnum|null $emptyStateIcon = 'heroicon-o-chart-bar';

    public function isEmpty(): bool
    {
        return array_sum($this->getCachedData()['datasets'][0]['data'] ?? []) === 0;
    }
}
