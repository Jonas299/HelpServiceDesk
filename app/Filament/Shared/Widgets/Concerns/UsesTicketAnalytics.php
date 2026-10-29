<?php

namespace App\Filament\Shared\Widgets\Concerns;

use App\Domain\Tickets\DTOs\ReportFilters;
use App\Domain\Tickets\Services\TicketAnalyticsService;
use Filament\Widgets\Concerns\InteractsWithPageFilters;

trait UsesTicketAnalytics
{
    use InteractsWithPageFilters;

    protected function reportFilters(): ReportFilters
    {
        return ReportFilters::fromArray($this->pageFilters ?? []);
    }

    protected function analytics(): TicketAnalyticsService
    {
        return app(TicketAnalyticsService::class);
    }
}
