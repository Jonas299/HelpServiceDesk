<?php

namespace App\Filament\Admin\Resources\PriorityGuidelines;

use App\Domain\Tickets\Services\PriorityGuidelineService;
use App\Models\PriorityGuideline;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class PriorityGuidelineResource extends Resource
{
    protected static ?string $model = PriorityGuideline::class;

    protected static ?string $modelLabel = 'lineamiento de prioridad';

    protected static ?string $pluralModelLabel = 'Prioridades y tiempos';

    protected static string|\UnitEnum|null $navigationGroup = 'Administración';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-clock';

    protected static ?int $navigationSort = 4;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Textarea::make('criteria')->label('Cuándo usar esta prioridad')->rows(4)->required()->maxLength(2000)->columnSpanFull(),
            TextInput::make('response_minutes')->label('Tiempo máximo de primera respuesta')->requiredWith(['resolution_minutes', 'calendar'])->numeric()->integer()->minValue(1)->maxValue(525600)->suffix('min')->placeholder('Sin definir'),
            TextInput::make('resolution_minutes')->label('Tiempo objetivo de solución')->requiredWith(['response_minutes', 'calendar'])->gte('response_minutes')->numeric()->integer()->minValue(1)->maxValue(525600)->suffix('min')->placeholder('Sin definir'),
            Select::make('calendar')->label('Cómputo del tiempo')->requiredWith(['response_minutes', 'resolution_minutes'])->options(['continuous' => 'Horas corridas (24/7)'])->placeholder('Sin definir')->helperText('Define ambos tiempos y su cómputo para publicar los objetivos. 60 min = 1 h; 1440 min = 24 h.')->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('priority')->label('Prioridad')->badge(),
            TextColumn::make('criteria')->label('Criterio')->wrap()->limit(100),
            TextColumn::make('response_minutes')->label('Primera respuesta')->formatStateUsing(fn ($state) => app(PriorityGuidelineService::class)->duration($state))->placeholder('Sin definir'),
            TextColumn::make('resolution_minutes')->label('Solución')->formatStateUsing(fn ($state) => app(PriorityGuidelineService::class)->duration($state))->placeholder('Sin definir'),
            TextColumn::make('calendar')->label('Cómputo')->formatStateUsing(fn () => 'Horas corridas (24/7)')->placeholder('Sin definir'),
        ])->recordActions([EditAction::make()->modalHeading(fn (PriorityGuideline $record) => 'Lineamientos: '.$record->priority->getLabel())->using(fn ($record, array $data) => app(PriorityGuidelineService::class)->save(auth()->user(), $record, $data))]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ManagePriorityGuidelines::route('/')];
    }
}
