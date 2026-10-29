<?php

namespace App\Domain\Companies\Repositories;

use App\Domain\Companies\Contracts\CompanyRepositoryInterface;
use App\Models\Company;

class EloquentCompanyRepository implements CompanyRepositoryInterface
{
    public function persist(?Company $record, array $attributes): Company
    {
        $record ??= new Company;
        $record->fill($attributes)->save();

        return $record;
    }

    public function findActive(?int $id): Company
    {
        return Company::query()->where('active', true)->findOrFail($id);
    }
}
