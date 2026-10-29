<?php

namespace App\Filament\Shared\Resources;

use App\Domain\Tickets\Contracts\TicketRepositoryInterface;
use App\Domain\Tickets\Enums\TicketPriority;
use App\Domain\Tickets\Enums\TicketStatus;
use App\Domain\Tickets\Services\PriorityGuidelineService;
use App\Domain\Tickets\Services\TicketObserverService;
use App\Models\Company;
use App\Models\Ticket;
use App\Models\TicketCategory;
use App\Models\User;
use App\Rules\ContactPhone;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/** @extends resource<Ticket> */
abstract class BaseTicketResource extends Resource
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-ticket';

    protected static string|\UnitEnum|null $navigationGroup = 'Soporte';

    protected static ?int $navigationSort = 2;

    protected static ?string $model = Ticket::class;

    protected static ?string $modelLabel = 'ticket';

    protected static ?string $pluralModelLabel = 'Tickets';

    protected static ?string $recordTitleAttribute = 'ticket_number';

    protected static ?string $slug = 'tickets';

    /** @return Builder<Ticket> */
    public static function getEloquentQuery(): Builder
    {
        return app(TicketRepositoryInterface::class)->visibleTo(auth()->user());
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('company_id')->label('Empresa')->options(fn () => Company::query()->where('active', true)->pluck('name', 'id'))->required(fn () => auth()->user()->isInternal())->visible(fn () => auth()->user()->isInternal())->searchable()->live()->afterStateUpdated(fn (Set $set) => $set('observer_ids', [])),
            TextInput::make('subject')->label('Asunto')->required()->maxLength(255),
            TextInput::make('contact_phone')->label('Número de contacto')->tel()->prefixIcon('heroicon-o-phone')->placeholder('+1 (809) 555-0123')->maxLength(40)->rules([new ContactPhone])->helperText('Opcional. Incluye el código de país si corresponde.'),
            Select::make('category_id')->label('Categoría')->options(fn () => TicketCategory::query()->where('active', true)->pluck('name', 'id'))->required()->searchable(),
            Select::make('priority')->label('Prioridad')->options(TicketPriority::class)->default('medium')->required()->live(),
            View::make('filament.tickets.priority-guideline')->viewData(fn (Get $get) => ['guide' => app(PriorityGuidelineService::class)->describe($get('priority'))])->columnSpanFull(),
            Select::make('observer_ids')->label('Observadores')->multiple()->searchable()->preload()->maxItems(TicketObserverService::MAX_OBSERVERS)->columnSpanFull()
                ->options(fn (Get $get) => app(TicketObserverService::class)->creationOptions(auth()->user(), filled($get('company_id')) ? (int) $get('company_id') : null))
                ->helperText('Recibirán actualizaciones y podrán participar en la conversación. Los clientes solo pueden seleccionar usuarios de su empresa.'),
            Textarea::make('description')->label('Descripción')->required()->maxLength(20000)->rows(8)->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('ticket_number')->label('Número')->icon('heroicon-o-ticket')->color('primary')->weight('semibold')->searchable()->sortable(), TextColumn::make('subject')->label('Asunto')->wrap()->searchable()->limit(60), TextColumn::make('company.name')->label('Empresa')->visible(fn () => auth()->user()->isInternal()), TextColumn::make('category.name')->label('Categoría'), TextColumn::make('priority')->label('Prioridad')->badge(), TextColumn::make('status')->label('Estado')->badge(), TextColumn::make('assignee.name')->label('Agente')->icon('heroicon-o-user-circle')->placeholder('Sin asignar'), TextColumn::make('created_at')->label('Creado')->dateTime('d/m/Y H:i')->sortable(),
        ])->filters([
            Filter::make('observing')->label('Tickets que observo')->query(fn (Builder $query) => $query->whereHas('observerMemberships', fn (Builder $memberships) => $memberships->where('user_id', auth()->id()))),
            SelectFilter::make('status')->label('Estado')->options(TicketStatus::class), SelectFilter::make('priority')->label('Prioridad')->options(TicketPriority::class),
            SelectFilter::make('category_id')->label('Categoría')->options(fn () => TicketCategory::query()->pluck('name', 'id')),
            SelectFilter::make('company_id')->label('Empresa')->options(fn () => auth()->user()->isInternal() ? Company::query()->pluck('name', 'id') : [])->visible(fn () => auth()->user()->isInternal()),
            SelectFilter::make('assigned_to')->label('Agente')->options(fn () => User::query()->where('role', 'agent')->where('active', true)->pluck('name', 'id'))->visible(fn () => auth()->user()->isInternal()),
            Filter::make('dates')->label('Fechas')->schema([DatePicker::make('from')->label('Desde'), DatePicker::make('until')->label('Hasta')])->query(fn (Builder $query, array $data) => $query->when($data['from'] ?? null, fn (Builder $q, $date) => $q->whereDate('created_at', '>=', $date))->when($data['until'] ?? null, fn (Builder $q, $date) => $q->whereDate('created_at', '<=', $date))),
        ])->recordActions([ViewAction::make()->label('Ver ticket')->icon('heroicon-o-arrow-up-right')])->striped()->emptyStateHeading('No hay tickets para mostrar')->emptyStateDescription('Crea una solicitud o ajusta los filtros para comenzar.')->emptyStateIcon('heroicon-o-inbox-stack')->defaultSort('created_at', 'desc');
    }
}
