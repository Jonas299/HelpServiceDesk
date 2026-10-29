<?php

namespace Database\Factories;

use App\Models\Ticket;
use App\Models\TicketEvent;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class TicketEventFactory extends Factory
{
    protected $model = TicketEvent::class;

    public function definition(): array
    {
        return ['ticket_id' => Ticket::factory(), 'actor_id' => User::factory(), 'event_type' => 'created'];
    }
}
