<?php

namespace App\Models;

use App\Domain\Tickets\Support\CommentContent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\HtmlString;

/**
 * @property Ticket|null $ticket
 * @property User $user
 */
class TicketComment extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = ['ticket_id', 'user_id', 'body', 'is_internal', 'is_rich_text'];

    protected function casts(): array
    {
        return ['is_internal' => 'boolean', 'is_rich_text' => 'boolean'];
    }

    /** @return BelongsTo<Ticket, $this> */
    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        return $query->whereIn('ticket_id', Ticket::query()->visibleTo($user)->select('id'))->when(! $user->isInternal(), fn (Builder $q) => $q->where('is_internal', false));
    }

    public function renderedBody(): HtmlString
    {
        return app(CommentContent::class)->render($this->body, $this->is_rich_text);
    }
}
