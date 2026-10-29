<?php

namespace App\Filament\Shared\Pages;

use App\Domain\Tickets\DTOs\CreateTicketData;
use App\Domain\Tickets\Services\TicketService;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

abstract class CreateTicket extends CreateRecord
{
    protected function handleRecordCreation(array $data): Model
    {
        return app(TicketService::class)->create(auth()->user(), CreateTicketData::fromArray($data));
    }

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('view', ['record' => $this->record]);
    }
}
