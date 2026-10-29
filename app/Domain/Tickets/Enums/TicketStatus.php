<?php

namespace App\Domain\Tickets\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum TicketStatus: string implements HasColor, HasIcon, HasLabel
{
    case New = 'new';
    case Assigned = 'assigned';
    case InProgress = 'in_progress';
    case WaitingCustomer = 'waiting_customer';
    case Resolved = 'resolved';
    case Closed = 'closed';
    case Cancelled = 'cancelled';

    public function getLabel(): string
    {
        return match ($this) {
            self::New => 'Nuevo',self::Assigned => 'Asignado',self::InProgress => 'En proceso',self::WaitingCustomer => 'Esperando cliente',self::Resolved => 'Resuelto',self::Closed => 'Cerrado',self::Cancelled => 'Cancelado'
        };
    }

    /** @return list<self> */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::New => [self::Assigned, self::InProgress, self::Cancelled],
            self::Assigned => [self::InProgress, self::Cancelled],
            self::InProgress => [self::WaitingCustomer, self::Resolved, self::Cancelled],
            self::WaitingCustomer => [self::InProgress, self::Cancelled],
            self::Resolved => [self::Closed, self::InProgress],
            default => [],
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::New => 'primary',
            self::Assigned => 'info',
            self::InProgress => 'primary',
            self::WaitingCustomer => 'warning',
            self::Resolved => 'success',
            self::Closed => 'gray',
            self::Cancelled => 'danger',
        };
    }

    public function getIcon(): string
    {
        return match ($this) {
            self::New => 'heroicon-o-sparkles',
            self::Assigned => 'heroicon-o-user-circle',
            self::InProgress => 'heroicon-o-arrow-path',
            self::WaitingCustomer => 'heroicon-o-chat-bubble-left-right',
            self::Resolved => 'heroicon-o-check-circle',
            self::Closed => 'heroicon-o-lock-closed',
            self::Cancelled => 'heroicon-o-x-circle',
        };
    }

    public function allowsAssignment(): bool
    {
        return ! in_array($this, [self::Closed, self::Cancelled, self::Resolved], true);
    }
}
