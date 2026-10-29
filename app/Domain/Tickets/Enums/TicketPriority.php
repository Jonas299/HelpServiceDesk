<?php

namespace App\Domain\Tickets\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum TicketPriority: string implements HasColor, HasIcon, HasLabel
{
    case Low = 'low';
    case Medium = 'medium';
    case High = 'high';
    case Critical = 'critical';

    public function getLabel(): string
    {
        return match ($this) {
            self::Low => 'Baja',self::Medium => 'Media',self::High => 'Alta',self::Critical => 'Crítica'
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Low => 'info',
            self::Medium => 'primary',
            self::High => 'warning',
            self::Critical => 'danger',
        };
    }

    public function getIcon(): string
    {
        return match ($this) {
            self::Low => 'heroicon-o-arrow-down',
            self::Medium => 'heroicon-o-minus',
            self::High => 'heroicon-o-arrow-up',
            self::Critical => 'heroicon-o-exclamation-triangle',
        };
    }
}
