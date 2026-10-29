<?php

namespace App\Models;

use App\Domain\Tickets\Enums\TicketPriority;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/** @property TicketPriority $priority */
class PriorityGuideline extends Model
{
    use SoftDeletes;

    protected $fillable = ['criteria', 'response_minutes', 'resolution_minutes', 'calendar'];

    protected function casts(): array
    {
        return ['priority' => TicketPriority::class, 'response_minutes' => 'integer', 'resolution_minutes' => 'integer'];
    }

    public function hasTargets(): bool
    {
        return $this->response_minutes !== null && $this->resolution_minutes !== null && $this->calendar === 'continuous';
    }
}
