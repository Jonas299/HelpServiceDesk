<?php

namespace App\Domain\Companies\Contracts;

use App\Models\Company;

interface CompanyRepositoryInterface
{
    public function persist(?Company $record, array $attributes): Company;

    public function findActive(?int $id): Company;
}
