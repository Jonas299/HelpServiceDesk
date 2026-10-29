<?php

namespace App\Filament\Shared\Widgets;

class TicketTrendChart extends AnalyticsChart
{
    protected ?string $heading = 'Actividad de soporte';

    protected ?string $description = 'Tickets creados cada día dentro del período seleccionado.';

    protected ?string $maxHeight = '300px';

    protected int|string|array $columnSpan = 'full';

    protected function getType(): string
    {
        return 'line';
    }

    protected function getData(): array
    {
        $data = $this->analytics()->trend(auth()->user(), $this->reportFilters());

        return ['labels' => $data['labels'], 'datasets' => [['label' => 'Tickets creados', 'data' => $data['values'], 'borderColor' => '#6366f1', 'backgroundColor' => 'rgba(99,102,241,0.10)', 'fill' => true, 'tension' => 0.35, 'pointRadius' => 2, 'pointHoverRadius' => 6, 'borderWidth' => 3]]];
    }

    protected function getOptions(): array
    {
        return ['maintainAspectRatio' => false, 'plugins' => ['legend' => ['display' => false]], 'scales' => ['y' => ['beginAtZero' => true, 'ticks' => ['precision' => 0]], 'x' => ['grid' => ['display' => false], 'ticks' => ['maxTicksLimit' => 14]]]];
    }
}
