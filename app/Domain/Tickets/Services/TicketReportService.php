<?php

namespace App\Domain\Tickets\Services;

use App\Domain\Tickets\DTOs\ReportFilters;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class TicketReportService
{
    public function __construct(private TicketAnalyticsService $analytics) {}

    public function download(User $user, ReportFilters $filters): StreamedResponse
    {
        Gate::forUser($user)->authorize('viewReports');

        return response()->streamDownload(function () use ($user, $filters) {
            $output = fopen('php://output', 'w');
            if ($output === false) {
                throw new \RuntimeException('No se pudo crear el reporte.');
            }
            fwrite($output, "\xEF\xBB\xBF");
            fputcsv($output, ['Número', 'Asunto', 'Empresa', 'Categoría', 'Agente', 'Estado', 'Prioridad', 'Creado', 'Primera respuesta', 'Resuelto', 'Cerrado'], ',', '"', '');
            foreach ($this->analytics->query($user, $filters)->lazyById(500) as $ticket) {
                $row = [$ticket->ticket_number, $ticket->subject, $ticket->company->name, $ticket->category->name, $ticket->assigned_to ? $ticket->assignee->name : 'Sin asignar', $ticket->status->getLabel(), $ticket->priority->getLabel()];
                foreach (['created_at', 'first_responded_at', 'resolved_at', 'closed_at'] as $field) {
                    $row[] = $ticket->{$field}?->timezone('America/Santo_Domingo')->format('d/m/Y H:i') ?? '';
                }
                fputcsv($output, array_map(self::safeCell(...), $row), ',', '"', '');
            }
            fclose($output);
        }, 'tickets-'.$filters->values['from'].'-'.$filters->values['until'].'.csv', ['Content-Type' => 'text/csv; charset=UTF-8', 'Cache-Control' => 'no-store']);
    }

    private static function safeCell(string $value): string
    {
        return preg_match('/^[\s\x{FEFF}]*[=+@\-]/u', $value) ? "'".$value : $value;
    }
}
