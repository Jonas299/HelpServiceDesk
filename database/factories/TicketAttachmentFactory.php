<?php

namespace Database\Factories;

use App\Models\Ticket;
use App\Models\TicketAttachment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class TicketAttachmentFactory extends Factory
{
    protected $model = TicketAttachment::class;

    public function definition(): array
    {
        return ['public_id' => (string) Str::ulid(), 'ticket_id' => Ticket::factory(), 'user_id' => User::factory(), 'path' => 'testing/sample.txt', 'original_name' => 'sample.txt', 'mime_type' => 'text/plain', 'size' => 10];
    }
}
