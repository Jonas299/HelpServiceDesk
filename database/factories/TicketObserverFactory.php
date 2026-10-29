<?php

namespace Database\Factories;

use App\Domain\Users\Enums\Role;
use App\Models\Ticket;
use App\Models\TicketObserver;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class TicketObserverFactory extends Factory
{
    protected $model = TicketObserver::class;

    public function definition(): array
    {
        return ['ticket_id' => Ticket::factory(), 'user_id' => User::factory()->state(['role' => Role::Agent, 'company_id' => null]), 'added_by' => User::factory()->state(['role' => Role::Admin, 'company_id' => null])];
    }
}
