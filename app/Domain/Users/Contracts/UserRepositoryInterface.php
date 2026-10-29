<?php

namespace App\Domain\Users\Contracts;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

interface UserRepositoryInterface
{
    public function persist(?User $record, array $attributes): User;

    public function findAssignableAgent(int $id): User;

    /** @return Builder<User> */
    public function eligibleObservers(int $companyId): Builder;
}
