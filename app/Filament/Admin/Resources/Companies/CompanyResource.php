<?php

namespace App\Filament\Admin\Resources\Companies;

use App\Filament\Shared\Resources\ConfigurationResource;
use App\Models\Company;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class CompanyResource extends ConfigurationResource
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-building-office-2';

    protected static string|\UnitEnum|null $navigationGroup = 'Administración';

    protected static ?int $navigationSort = 1;

    protected static ?string $model = Company::class;

    protected static ?string $modelLabel = 'Empresas';

    protected static ?string $pluralModelLabel = 'Empresas';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([TextInput::make('name')->label('Nombre')->required()->maxLength(255), TextInput::make('legal_name')->label('Razón social'), TextInput::make('tax_id')->label('RNC'), TextInput::make('email')->label('Correo')->email(), TextInput::make('phone')->label('Teléfono'), Textarea::make('address')->label('Dirección'), Toggle::make('active')->label('Activo')->default(true)->required()]);
    }

    public static function table(Table $table): Table
    {
        return static::configurationActions($table->columns([TextColumn::make('name')->label('Nombre')->searchable()->sortable(), TextColumn::make('legal_name')->label('Razón social')->searchable(), TextColumn::make('tax_id')->label('RNC')->searchable(), TextColumn::make('email')->label('Correo'), TextColumn::make('phone')->label('Teléfono'), IconColumn::make('active')->label('Activo')->boolean()]));
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ManageCompanies::route('/')];
    }
}
