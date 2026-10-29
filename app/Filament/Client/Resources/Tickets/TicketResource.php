<?php

namespace App\Filament\Client\Resources\Tickets;

use App\Filament\Shared\Resources\BaseTicketResource;

class TicketResource extends BaseTicketResource
{
    public static function getPages(): array
    {
        return ['index' => Pages\ListTickets::route('/'), 'create' => Pages\CreateTicket::route('/create'), 'view' => Pages\ViewTicket::route('/{record}')];
    }
}
