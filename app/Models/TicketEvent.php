<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property array|null $old_values
 * @property array|null $new_values
 * @property array|null $metadata
 */
class TicketEvent extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = ['ticket_id', 'actor_id', 'event_type', 'old_values', 'new_values', 'metadata'];

    protected function casts(): array
    {
        return ['old_values' => 'array', 'new_values' => 'array', 'metadata' => 'array', 'created_at' => 'datetime'];
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id')->withTrashed();
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new \LogicException('La auditoría es inmutable.'));
        static::deleting(fn () => throw new \LogicException('La auditoría es inmutable.'));
    }
}
