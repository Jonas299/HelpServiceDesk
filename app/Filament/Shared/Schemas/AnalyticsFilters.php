<?php

namespace App\Filament\Shared\Schemas;

use App\Domain\Tickets\Enums\TicketPriority;
use App\Domain\Tickets\Enums\TicketStatus;
use App\Models\Company;
use App\Models\TicketCategory;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Actions;

final class AnalyticsFilters
{
    public static function fields(User $user, bool $detailed = false): array
    {
        $fields = [
            DatePicker::make('from')->label('Desde')->default(now('America/Santo_Domingo')->subDays(29)->toDateString())->required()->native(false)->displayFormat('d/m/Y')->prefixIcon('heroicon-o-calendar-days'),
            DatePicker::make('until')->label('Hasta')->default(now('America/Santo_Domingo')->toDateString())->required()->afterOrEqual('from')->native(false)->displayFormat('d/m/Y')->prefixIcon('heroicon-o-calendar-days'),
            Select::make('company_id')->label('Empresa')->placeholder('Todas las empresas')->options(fn () => $user->isInternal() ? Company::withTrashed()->pluck('name', 'id') : [])->searchable()->visible($user->isInternal()),
        ];
        if ($detailed) {
            $fields = [...$fields,
                Select::make('assigned_to')->label('Agente')->options(fn () => User::withTrashed()->where('role', 'agent')->pluck('name', 'id'))->searchable()->placeholder('Todos'),
                Select::make('category_id')->label('Categoría')->options(fn () => TicketCategory::withTrashed()->pluck('name', 'id'))->searchable()->placeholder('Todas'),
                Select::make('status')->label('Estado')->options(TicketStatus::class)->placeholder('Todos'),
                Select::make('priority')->label('Prioridad')->options(TicketPriority::class)->placeholder('Todas'),
            ];
        }
        $fields[] = Actions::make([
            Action::make('applyFilters')->label('Aplicar filtros')->icon('heroicon-o-funnel')->action('applyFilters'),
            Action::make('resetFilters')->label('Restablecer')->color('gray')->icon('heroicon-o-arrow-path')->action('resetFilters'),
        ])->columnSpanFull();

        return $fields;
    }
}
