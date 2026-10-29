<?php

namespace App\Filament\Shared\Widgets;

use Livewire\Attributes\Locked;

class TicketDistributionChart extends AnalyticsChart
{
    #[Locked]
    public string $dimension = 'status';

    protected ?string $maxHeight = '300px';

    public function getHeading(): string
    {
        return match ($this->dimension) {
            'status' => 'Estado de los tickets','priority' => 'Distribución por prioridad',
            'company' => 'Empresas con más solicitudes','agent' => 'Carga por agente',
            'category' => 'Categorías más solicitadas',
            default => throw new \InvalidArgumentException('Dimensión no permitida.'),
        };
    }

    public function getDescription(): string
    {
        return in_array($this->dimension, ['status', 'priority'], true) ? 'Distribución del período seleccionado.' : 'Hasta 10 grupos con mayor volumen de tickets.';
    }

    protected function getType(): string
    {
        return in_array($this->dimension, ['status', 'priority'], true) ? 'doughnut' : 'bar';
    }

    protected function getData(): array
    {
        $data = $this->analytics()->distribution(auth()->user(), $this->reportFilters(), $this->dimension);

        return ['labels' => $data['labels'], 'datasets' => [['label' => 'Tickets', 'data' => $data['values'], 'backgroundColor' => $data['colors'], 'borderWidth' => 0, 'hoverOffset' => 6, 'borderRadius' => $this->getType() === 'bar' ? 6 : 0]]];
    }

    protected function getOptions(): array
    {
        $options = ['maintainAspectRatio' => false, 'plugins' => ['legend' => ['display' => $this->getType() === 'doughnut', 'position' => 'bottom', 'labels' => ['usePointStyle' => true, 'padding' => 18, 'boxWidth' => 8]]]];
        if ($this->getType() === 'doughnut') {
            $options['cutout'] = '72%';
        } else {
            $options['indexAxis'] = 'y';
            $options['scales'] = ['x' => ['beginAtZero' => true, 'ticks' => ['precision' => 0]], 'y' => ['grid' => ['display' => false]]];
        }

        return $options;
    }
}
