<?php

namespace App\Policies;

use App\Domain\Users\Enums\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class ConfigurationPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isEnabled() && $user->role === Role::Admin;
    }

    public function view(User $user, Model $record): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function update(User $user, Model $record): bool
    {
        return $this->viewAny($user);
    }

    public function delete(User $user, Model $record): bool
    {
        return $this->viewAny($user) && ! ($record instanceof User && $record->is($user));
    }

    public function restore(User $user, Model $record): bool
    {
        return $this->viewAny($user);
    }

    public function forceDelete(User $user, Model $record): bool
    {
        return false;
    }
}
