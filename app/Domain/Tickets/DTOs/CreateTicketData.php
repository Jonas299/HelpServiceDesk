<?php

namespace App\Domain\Tickets\DTOs;

use App\Domain\Tickets\Enums\TicketPriority;
use Illuminate\Validation\ValidationException;

final readonly class CreateTicketData
{
    public function __construct(public string $subject, public string $description, public int $categoryId, public TicketPriority $priority = TicketPriority::Medium, public ?int $companyId = null, public ?string $contactPhone = null, public array $observerIds = []) {}

    public static function fromArray(array $data): self
    {
        if (! is_array($data['observer_ids'] ?? [])) {
            throw ValidationException::withMessages(['observer_ids' => 'Selecciona una lista de observadores válida.']);
        }

        return new self($data['subject'], $data['description'], (int) $data['category_id'], ($data['priority'] ?? null) instanceof TicketPriority ? $data['priority'] : TicketPriority::from($data['priority'] ?? 'medium'), isset($data['company_id']) ? (int) $data['company_id'] : null, filled($data['contact_phone'] ?? null) ? trim($data['contact_phone']) : null, $data['observer_ids'] ?? []);
    }
}
