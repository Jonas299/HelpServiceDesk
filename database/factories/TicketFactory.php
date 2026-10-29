<?php

namespace Database\Factories;

use App\Domain\Tickets\Enums\TicketPriority;
use App\Domain\Tickets\Enums\TicketStatus;
use App\Models\Company;
use App\Models\Ticket;
use App\Models\TicketCategory;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class TicketFactory extends Factory
{
    protected $model = Ticket::class;

    public function definition(): array
    {
        return ['public_id' => (string) Str::ulid(), 'ticket_number' => 'TK-'.now()->year.'-'.fake()->unique()->numerify('#########'), 'company_id' => Company::factory(), 'created_by' => User::factory(), 'category_id' => TicketCategory::factory(), 'subject' => fake()->sentence(), 'description' => fake()->paragraph(), 'priority' => TicketPriority::Medium, 'status' => TicketStatus::New, 'source' => 'portal'];
    }
}
