<?php

namespace App\Domain\Tickets\DTOs;

use App\Domain\Tickets\Enums\TicketPriority;
use App\Domain\Tickets\Enums\TicketStatus;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

final readonly class ReportFilters
{
    private function __construct(public array $values) {}

    public static function fromArray(array $data = []): self
    {
        // Filament DatePicker keeps midnight timestamps in its raw Livewire state.
        foreach (['from', 'until'] as $field) {
            if (is_string($data[$field] ?? null) && preg_match('/^\d{4}-\d{2}-\d{2} 00:00:00$/', $data[$field])) {
                $data[$field] = substr($data[$field], 0, 10);
            }
        }
        $data['from'] = filled($data['from'] ?? null) ? $data['from'] : CarbonImmutable::now('America/Santo_Domingo')->subDays(29)->toDateString();
        $data['until'] = filled($data['until'] ?? null) ? $data['until'] : CarbonImmutable::now('America/Santo_Domingo')->toDateString();
        $values = Validator::make($data, [
            'from' => ['required', 'date_format:Y-m-d'],
            'until' => ['required', 'date_format:Y-m-d', 'after_or_equal:from'],
            'company_id' => ['nullable', 'integer', 'min:1'],
            'assigned_to' => ['nullable', 'integer', 'min:1'],
            'category_id' => ['nullable', 'integer', 'min:1'],
            'status' => ['nullable', Rule::enum(TicketStatus::class)],
            'priority' => ['nullable', Rule::enum(TicketPriority::class)],
        ])->validate();
        if (CarbonImmutable::parse($values['from'])->diffInDays(CarbonImmutable::parse($values['until'])) > 365) {
            throw ValidationException::withMessages(['until' => 'Selecciona un período de hasta 366 días.']);
        }

        return new self(array_filter($values, fn ($value) => $value !== null && $value !== ''));
    }
}
