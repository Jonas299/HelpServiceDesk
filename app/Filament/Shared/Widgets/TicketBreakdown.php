<?php

namespace App\Filament\Shared\Widgets;

use App\Filament\Shared\Widgets\Concerns\UsesTicketAnalytics;
use Filament\Widgets\Widget;

class TicketBreakdown extends Widget
{
    use UsesTicketAnalytics;

    protected string $view = 'filament.widgets.ticket-breakdown';

    protected int|string|array $columnSpan = 'full';

    protected function getViewData(): array
    {
        return ['recent' => $this->analytics()->query(auth()->user(), $this->reportFilters())->latest('tickets.created_at')->limit(5)->get()];
    }
}
