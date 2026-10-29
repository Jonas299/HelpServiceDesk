<?php

namespace App\Domain\Tickets\Services;

use App\Domain\Tickets\Contracts\TicketRepositoryInterface;
use App\Domain\Tickets\DTOs\ReportFilters;
use App\Domain\Tickets\Enums\TicketPriority;
use App\Domain\Tickets\Enums\TicketStatus;
use App\Models\Ticket;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

final class TicketAnalyticsService
{
    public function __construct(private TicketRepositoryInterface $tickets) {}

    /** @return Builder<Ticket> */
    public function query(User $user, ReportFilters $filters): Builder
    {
        return $this->tickets->visibleTo($user, $filters->values);
    }

    public function summary(User $user, ReportFilters $filters): array
    {
        $row = $this->query($user, $filters)->withoutEagerLoads()->toBase()->selectRaw(<<<'SQL'
            COUNT(*) AS total,
            COUNT(*) FILTER (WHERE status NOT IN ('resolved','closed','cancelled')) AS open,
            COUNT(*) FILTER (WHERE status = 'new') AS new,
            COUNT(*) FILTER (WHERE status = 'in_progress') AS in_progress,
            COUNT(*) FILTER (WHERE status = 'waiting_customer') AS waiting,
            COUNT(*) FILTER (WHERE priority = 'critical' AND status NOT IN ('resolved','closed','cancelled')) AS critical,
            COUNT(*) FILTER (WHERE status IN ('resolved','closed')) AS completed,
            AVG(EXTRACT(EPOCH FROM (first_responded_at - created_at))/3600.0)
                FILTER (WHERE first_responded_at >= created_at) AS response_hours,
            AVG(EXTRACT(EPOCH FROM (resolved_at - created_at))/3600.0)
                FILTER (WHERE resolved_at >= created_at) AS resolution_hours
            SQL)->first();
        $result = (array) $row;
        foreach (['total', 'open', 'new', 'in_progress', 'waiting', 'critical', 'completed'] as $key) {
            $result[$key] = (int) $result[$key];
        }
        $result['completion_rate'] = $result['total'] ? round($result['completed'] / $result['total'] * 100, 1) : 0;
        foreach (['response_hours', 'resolution_hours'] as $key) {
            $result[$key] = $result[$key] !== null ? round((float) $result[$key], 1) : null;
        }

        return $result;
    }

    public function distribution(User $user, ReportFilters $filters, string $dimension): array
    {
        $query = $this->query($user, $filters)->withoutEagerLoads();
        if (in_array($dimension, ['status', 'priority'], true)) {
            $counts = $query->selectRaw($dimension.', COUNT(*) AS aggregate')->groupBy($dimension)->toBase()->pluck('aggregate', $dimension);
            $cases = $dimension === 'status' ? TicketStatus::cases() : TicketPriority::cases();
            $palette = $dimension === 'status' ? ['#6366f1', '#38bdf8', '#8b5cf6', '#f59e0b', '#10b981', '#64748b', '#f43f5e'] : ['#38bdf8', '#6366f1', '#f59e0b', '#f43f5e'];

            return ['labels' => array_map(fn ($case) => $case->getLabel(), $cases), 'values' => array_map(fn ($case) => (int) ($counts[$case->value] ?? 0), $cases), 'colors' => $palette];
        }
        [$table,$foreignKey,$fallback] = match ($dimension) {
            'company' => ['companies', 'company_id', 'Empresa archivada'],
            'agent' => ['users', 'assigned_to', 'Sin asignar'],
            'category' => ['ticket_categories', 'category_id', 'Sin categoría'],
            default => throw new \InvalidArgumentException('Dimensión de reporte no permitida.'),
        };
        $rows = $query->leftJoin($table, "tickets.{$foreignKey}", '=', $table.'.id')
            ->selectRaw("COALESCE({$table}.name, ?) AS label, COUNT(*) AS aggregate", [$fallback])
            ->groupBy($table.'.id', $table.'.name')->orderByDesc('aggregate')->orderBy('label')->limit(10)->toBase()->get();

        return ['labels' => $rows->pluck('label')->all(), 'values' => $rows->pluck('aggregate')->map(fn ($value) => (int) $value)->all(), 'colors' => ['#6366f1', '#818cf8', '#a78bfa', '#38bdf8', '#2dd4bf', '#34d399', '#fbbf24', '#fb923c', '#fb7185', '#94a3b8']];
    }

    public function trend(User $user, ReportFilters $filters): array
    {
        $expression = "to_char(tickets.created_at AT TIME ZONE 'America/Santo_Domingo', 'YYYY-MM-DD')";
        $rows = $this->query($user, $filters)->withoutEagerLoads()->selectRaw("{$expression} AS day, COUNT(*) AS aggregate")->groupByRaw($expression)->toBase()->pluck('aggregate', 'day');
        $labels = [];
        $values = [];
        $end = CarbonImmutable::parse($filters->values['until']);
        for ($day = CarbonImmutable::parse($filters->values['from']); $day->lte($end); $day = $day->addDay()) {
            $labels[] = $day->format('d/m');
            $values[] = (int) ($rows[$day->toDateString()] ?? 0);
        }

        return ['labels' => $labels, 'values' => $values];
    }
}
