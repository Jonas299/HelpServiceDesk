<?php

namespace App\Domain\Tickets\Services;

use App\Domain\Tickets\Enums\TicketPriority;
use App\Models\PriorityGuideline;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

final class PriorityGuidelineService
{
    public function save(User $actor, PriorityGuideline $guideline, array $data): PriorityGuideline
    {
        Gate::forUser($actor)->authorize('update', $guideline);
        foreach (['response_minutes', 'resolution_minutes', 'calendar'] as $field) {
            if (array_key_exists($field, $data) && blank($data[$field])) {
                $data[$field] = null;
            }
        }
        $values = Validator::make($data, [
            'criteria' => 'required|string|max:2000',
            'response_minutes' => 'nullable|required_with:resolution_minutes,calendar|integer|min:1|max:525600',
            'resolution_minutes' => 'nullable|required_with:response_minutes,calendar|integer|min:1|max:525600|gte:response_minutes',
            'calendar' => ['nullable', 'required_with:response_minutes,resolution_minutes', Rule::in(['continuous'])],
        ])->validate();

        return DB::transaction(function () use ($guideline, $values) {
            $guideline->fill($values)->save();

            return $guideline->refresh();
        });
    }

    public function describe(TicketPriority|string|null $priority): array
    {
        $priority = $priority instanceof TicketPriority ? $priority : (TicketPriority::tryFrom($priority ?? '') ?? TicketPriority::Medium);
        $guideline = PriorityGuideline::query()->where('priority', $priority->value)->first();

        return [
            'priority' => $priority, 'criteria' => $guideline->criteria ?? 'Consulta al equipo de soporte para clasificar esta incidencia.',
            'configured' => $guideline?->hasTargets() ?? false,
            'response' => $guideline?->hasTargets() ? $this->duration($guideline->response_minutes) : 'Sin definir',
            'resolution' => $guideline?->hasTargets() ? $this->duration($guideline->resolution_minutes) : 'Sin definir',
            'calendar' => $guideline?->hasTargets() ? 'Horas corridas (24/7)' : null,
        ];
    }

    public function duration(?int $minutes): string
    {
        if ($minutes === null) {
            return 'Sin definir';
        }
        $hours = intdiv($minutes, 60);
        $remaining = $minutes % 60;

        return implode(' ', array_filter([$hours ? $hours.' h' : null, $remaining ? $remaining.' min' : null]));
    }
}
