<?php

namespace App\Domain\Configuration\Services;

use App\Domain\Companies\Contracts\CompanyRepositoryInterface;
use App\Domain\Users\Contracts\UserRepositoryInterface;
use App\Domain\Users\Enums\Role;
use App\Models\Company;
use App\Models\TicketCategory;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

final class ConfigurationService
{
    public function __construct(private CompanyRepositoryInterface $companies, private UserRepositoryInterface $users) {}

    public function save(User $actor, string $model, array $data, ?Model $record = null): Model
    {
        if ($record !== null && $record::class !== $model) {
            throw new \InvalidArgumentException('Registro incompatible.');
        }
        Gate::forUser($actor)->authorize($record ? 'update' : 'create', $record ?? $model);

        return DB::transaction(function () use ($model, $data, $record) {
            $attributes = Validator::make($data, $this->rules($model, $record, $data))->validate();
            if ($model === User::class) {
                if (empty($attributes['password'])) {
                    unset($attributes['password']);
                }

                if ($record !== null && ! $record instanceof User) {
                    throw new \InvalidArgumentException('Registro incompatible.');
                }

                return $this->users->persist($record, $attributes);
            }
            if ($model === Company::class) {
                if ($record !== null && ! $record instanceof Company) {
                    throw new \InvalidArgumentException('Registro incompatible.');
                }

                return $this->companies->persist($record, $attributes);
            }
            $record ??= new TicketCategory;
            $record->fill($attributes)->save();

            return $record;
        });
    }

    private function rules(string $model, ?Model $record, array $data): array
    {
        $common = ['name' => 'required|string|max:255', 'active' => 'required|boolean'];

        return array_replace($common, match ($model) {
            User::class => [
                'email' => ['required', 'email', 'max:255', Rule::unique('users')->ignore($record?->getKey())],
                'password' => [$record ? 'nullable' : 'required', 'string', 'min:12', 'max:255'],
                'role' => ['required', Rule::enum(Role::class)],
                'company_id' => [
                    'nullable',
                    Rule::requiredIf(($data['role'] ?? '') === Role::Client->value),
                    Rule::exists('companies', 'id')->whereNull('deleted_at')->where('active', true),
                ],
            ],
            Company::class => [
                'legal_name' => 'nullable|string|max:255',
                'tax_id' => 'nullable|string|max:255',
                'email' => 'nullable|email|max:255',
                'phone' => 'nullable|string|max:255',
                'address' => 'nullable|string|max:2000',
            ],
            TicketCategory::class => [
                'name' => ['required', 'string', 'max:255', Rule::unique('ticket_categories')->ignore($record?->getKey())],
            ],
            default => throw new \InvalidArgumentException('Modelo no permitido.'),
        });
    }
}
