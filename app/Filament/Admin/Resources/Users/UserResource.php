<?php

namespace App\Filament\Admin\Resources\Users;

use App\Domain\Users\Enums\Role;
use App\Filament\Shared\Resources\ConfigurationResource;
use App\Models\User;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class UserResource extends ConfigurationResource
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-users';

    protected static string|\UnitEnum|null $navigationGroup = 'Administración';

    protected static ?int $navigationSort = 2;

    protected static ?string $model = User::class;

    protected static ?string $modelLabel = 'Personal interno';

    protected static ?string $pluralModelLabel = 'Personal interno';

    protected static function isClientResource(): bool
    {
        return false;
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->where('role', static::isClientResource() ? '=' : '!=', Role::Client->value)->with('company');
    }

    public static function form(Schema $schema): Schema
    {
        $fields = [TextInput::make('name')->label('Nombre')->required()->maxLength(255), TextInput::make('email')->label('Correo')->email()->required()->maxLength(255), TextInput::make('password')->label('Contraseña')->password()->minLength(12)->required(fn (string $operation) => $operation === 'create')->dehydrated(fn ($state) => filled($state))];
        if (static::isClientResource()) {
            $fields[] = Select::make('company_id')->label('Empresa')->relationship('company', 'name', fn (Builder $query) => $query->where('active', true)->whereNull('deleted_at'))->searchable()->preload()->required();
            $fields[] = Hidden::make('role')->default(Role::Client->value)->in([Role::Client->value]);
        } else {
            $fields[] = Select::make('role')->label('Rol')->options(['admin' => 'Administrador', 'agent' => 'Agente'])->required()->in(['admin', 'agent']);
        }
        $fields[] = Toggle::make('active')->label('Activo')->default(true)->required();

        return $schema->components($fields);
    }

    public static function table(Table $table): Table
    {
        $columns = [TextColumn::make('name')->label('Nombre')->searchable()->sortable(), TextColumn::make('email')->label('Correo')->searchable()];
        $columns[] = static::isClientResource() ? TextColumn::make('company.name')->label('Empresa')->searchable()->sortable() : TextColumn::make('role')->label('Rol')->formatStateUsing(fn (Role $state) => $state === Role::Admin ? 'Administrador' : 'Agente');
        $columns[] = IconColumn::make('active')->label('Activo')->boolean();

        return static::configurationActions($table->columns($columns));
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ManageUsers::route('/')];
    }
}
