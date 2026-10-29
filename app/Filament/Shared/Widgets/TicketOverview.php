<?php

namespace App\Filament\Shared\Widgets;

use App\Filament\Shared\Widgets\Concerns\UsesTicketAnalytics;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class TicketOverview extends StatsOverviewWidget
{
    use UsesTicketAnalytics;

    protected static ?int $sort = 1;

    protected static bool $isLazy = false;

    protected function getStats(): array
    {
        $data = $this->analytics()->summary(auth()->user(), $this->reportFilters());

        return [
            Stat::make('Tickets abiertos', $data['open'])->icon('heroicon-o-inbox-stack')->description('Pendientes de solución')->descriptionIcon('heroicon-o-arrow-path')->color('primary'),
            Stat::make('En proceso', $data['in_progress'])->icon('heroicon-o-play-circle')->description($data['new'].' nuevos · '.$data['waiting'].' esperando cliente')->color('info'),
            Stat::make('Críticos abiertos', $data['critical'])->icon('heroicon-o-exclamation-triangle')->description('Requieren atención prioritaria')->color('danger'),
            Stat::make('Resueltos / cerrados', $data['completed'])->icon('heroicon-o-check-badge')->description($data['completion_rate'].'% de los tickets del período')->color('success'),
        ];
    }

    protected function getColumns(): int|array
    {
        return ['sm' => 2, 'xl' => 4];
    }
}
