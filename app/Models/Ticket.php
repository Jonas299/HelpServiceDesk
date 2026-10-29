<?php

namespace App\Models;

use App\Domain\Tickets\Enums\TicketPriority;
use App\Domain\Tickets\Enums\TicketStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property TicketStatus $status
 * @property TicketPriority $priority
 * @property Company $company
 * @property TicketCategory $category
 * @property User|null $assignee
 * @property User $creator
 */
class Ticket extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = ['public_id', 'ticket_number', 'company_id', 'created_by', 'assigned_to', 'category_id', 'subject', 'description', 'contact_phone', 'priority', 'status', 'source', 'first_responded_at', 'resolved_at', 'closed_at'];

    protected function casts(): array
    {
        return ['priority' => TicketPriority::class, 'status' => TicketStatus::class, 'first_responded_at' => 'datetime', 'resolved_at' => 'datetime', 'closed_at' => 'datetime'];
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    /** @return BelongsTo<Company, $this> */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class)->withTrashed();
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by')->withTrashed();
    }

    /** @return BelongsTo<User, $this> */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to')->withTrashed();
    }

    /** @return BelongsTo<TicketCategory, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(TicketCategory::class, 'category_id')->withTrashed();
    }

    /** @return HasMany<TicketComment, $this> */
    public function comments(): HasMany
    {
        return $this->hasMany(TicketComment::class);
    }

    /** @return HasMany<TicketEvent, $this> */
    public function events(): HasMany
    {
        return $this->hasMany(TicketEvent::class);
    }

    /** @return HasMany<TicketAttachment, $this> */
    public function attachments(): HasMany
    {
        return $this->hasMany(TicketAttachment::class);
    }

    /** @param Builder<Ticket> $query
     * @return Builder<Ticket>
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if (! $user->isEnabled()) {
            return $query->whereRaw('1 = 0');
        }

        return $user->isInternal() ? $query : $query->where('tickets.company_id', $user->company_id);
    }

    /** @return HasMany<TicketObserver, $this> */
    public function observerMemberships(): HasMany
    {
        return $this->hasMany(TicketObserver::class);
    }

    /** @return BelongsToMany<User, $this> */
    public function observers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'ticket_observers')->wherePivotNull('deleted_at')->withTimestamps();
    }

    /** @return list<int> */
    public function participantIds(): array
    {
        return array_values(array_unique(array_filter([$this->created_by, $this->assigned_to, ...$this->observerMemberships()->pluck('user_id')->all()])));
    }

    public function isParticipant(User $user): bool
    {
        return $this->created_by === $user->id || $this->assigned_to === $user->id || $this->isObserver($user);
    }

    /** @param Builder<Ticket> $query
     * @return Builder<Ticket>
     */
    public function scopeObservedBy(Builder $query, User $user): Builder
    {
        return $query->whereHas('observerMemberships', fn (Builder $memberships) => $memberships->where('user_id', $user->id));
    }

    public function isObserver(User $user): bool
    {
        return $this->observerMemberships()->where('user_id', $user->id)->exists();
    }
}
