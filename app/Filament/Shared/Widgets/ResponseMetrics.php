<?php

namespace App\Filament\Shared\Widgets;

use App\Filament\Shared\Widgets\Concerns\UsesTicketAnalytics;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class ResponseMetrics extends StatsOverviewWidget
{
    use UsesTicketAnalytics;

    protected function getStats(): array
    {
        $data = $this->analytics()->summary(auth()->user(), $this->reportFilters());

        return [
            Stat::make('Tickets del período', $data['total'])->icon('heroicon-o-ticket')->description('Según fecha de creación')->color('primary'),
            Stat::make('Primera respuesta media', $data['response_hours'] === null ? 'Sin datos' : $data['response_hours'].' h')->icon('heroicon-o-chat-bubble-left-right')->description('Solo respuestas públicas del equipo')->color('info'),
            Stat::make('Resolución media', $data['resolution_hours'] === null ? 'Sin datos' : $data['resolution_hours'].' h')->icon('heroicon-o-clock')->description('Tickets con fecha de resolución')->color('success'),
        ];
    }
}
