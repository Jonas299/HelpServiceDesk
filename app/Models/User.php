<?php

namespace App\Models;

use App\Domain\Users\Enums\Role;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * @property Role $role
 * @property Company|null $company
 */
class User extends Authenticatable implements FilamentUser
{
    use HasFactory, Notifiable, SoftDeletes;

    protected $fillable = ['name', 'email', 'password', 'company_id', 'role', 'active'];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return ['password' => 'hashed', 'email_verified_at' => 'datetime', 'role' => Role::class, 'active' => 'boolean'];
    }

    /** @return BelongsTo<Company, $this> */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function isInternal(): bool
    {
        return in_array($this->role, [Role::Admin, Role::Agent], true);
    }

    public function isEnabled(): bool
    {
        return $this->active && ! $this->trashed() && ($this->role !== Role::Client || ($this->company?->active && ! $this->company->trashed()));
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return $this->isEnabled() && ($panel->getId() === 'admin' ? $this->isInternal() : $this->role === Role::Client);
    }
}
