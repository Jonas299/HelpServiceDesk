<?php

namespace App\Filament\Shared\Resources;

use App\Domain\Configuration\Services\ConfigurationService;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Resource;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

abstract class ConfigurationResource extends Resource
{
    protected static function configurationActions(Table $table): Table
    {
        return $table->filters([TrashedFilter::make()])->recordActions([
            EditAction::make()->icon('heroicon-o-pencil-square')->using(fn ($record, array $data) => app(ConfigurationService::class)->save(auth()->user(), static::getModel(), $data, $record)),
            DeleteAction::make()->icon('heroicon-o-archive-box'),
            RestoreAction::make()->icon('heroicon-o-arrow-uturn-left'),
        ]);
    }

    public static function createAction(): CreateAction
    {
        return CreateAction::make()->icon('heroicon-o-plus')->using(fn (array $data) => app(ConfigurationService::class)->save(auth()->user(), static::getModel(), $data));
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->withoutGlobalScopes([SoftDeletingScope::class]);
    }
}
