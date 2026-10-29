<?php

namespace App\Filament\Admin\Resources\Categories;

use App\Filament\Shared\Resources\ConfigurationResource;
use App\Models\TicketCategory;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class CategoryResource extends ConfigurationResource
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-squares-plus';

    protected static string|\UnitEnum|null $navigationGroup = 'Administración';

    protected static ?int $navigationSort = 3;

    protected static ?string $model = TicketCategory::class;

    protected static ?string $modelLabel = 'Categorías';

    protected static ?string $pluralModelLabel = 'Categorías';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([TextInput::make('name')->label('Nombre')->required()->maxLength(255),  Toggle::make('active')->label('Activo')->default(true)->required()]);
    }

    public static function table(Table $table): Table
    {
        return static::configurationActions($table->columns([TextColumn::make('name')->label('Nombre')->searchable()->sortable(),  IconColumn::make('active')->label('Activo')->boolean()]));
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ManageCategories::route('/')];
    }
}
