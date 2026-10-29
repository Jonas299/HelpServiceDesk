<?php

namespace App\Domain\Users\Repositories;

use App\Domain\Users\Contracts\UserRepositoryInterface;
use App\Domain\Users\Enums\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class EloquentUserRepository implements UserRepositoryInterface
{
    public function persist(?User $record, array $attributes): User
    {
        $record ??= new User;
        $record->fill($attributes)->save();

        return $record;
    }

    public function findAssignableAgent(int $id): User
    {
        return User::query()->where('active', true)->where('role', Role::Agent->value)->findOrFail($id);
    }

    /** @return Builder<User> */
    public function eligibleObservers(int $companyId): Builder
    {
        return User::query()->where('active', true)->where(function (Builder $query) use ($companyId): void {
            $query->whereIn('role', [Role::Admin->value, Role::Agent->value])
                ->orWhere(fn (Builder $clients) => $clients->where('role', Role::Client->value)->where('company_id', $companyId)->whereHas('company', fn ($companies) => $companies->where('active', true)));
        });
    }
}
