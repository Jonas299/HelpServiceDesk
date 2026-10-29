<?php

namespace App\Filament\Admin\Resources\Clients;

use App\Filament\Admin\Resources\Users\UserResource;

class ClientResource extends UserResource
{
    protected static ?string $slug = 'clients';

    protected static ?string $modelLabel = 'Usuario cliente';

    protected static ?string $pluralModelLabel = 'Usuarios clientes';

    protected static ?int $navigationSort = 3;

    protected static function isClientResource(): bool
    {
        return true;
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ManageClients::route('/')];
    }
}
