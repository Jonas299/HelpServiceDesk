<?php

namespace Tests\Unit;

use App\Domain\Tickets\DTOs\CreateTicketData;
use App\Domain\Tickets\Enums\TicketPriority;
use PHPUnit\Framework\TestCase;

class ExampleTest extends TestCase
{
    public function test_creation_dto_accepts_enum_and_serialized_priority(): void
    {
        foreach (['high', TicketPriority::High] as $priority) {
            $data = CreateTicketData::fromArray(['subject' => 'Asunto', 'description' => 'Descripción', 'category_id' => 7, 'priority' => $priority]);
            $this->assertSame(TicketPriority::High, $data->priority);
            $this->assertNull($data->companyId);
        }
    }
}
