<?php

namespace App\Livewire\Concerns;

use App\Enums\CardioMetric;
use App\Enums\ExerciseType;
use App\Models\Exercise;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Doelen van een oefening invullen (<x-exercise-targets>), zoals in een workout of tijdens een training.
 * Een "item" heeft: key, id, exercise_id, sets, reps, weight, rest, seconds (type tijd),
 * minutes + metric + metric_value (cardio) en notes.
 */
trait EditsExerciseTargets
{
    /** Validatieregels voor één item; $prefix is bv. "items.*" of "adding" */
    protected function targetRules(string $prefix): array
    {
        return [
            "{$prefix}.sets" => ['nullable', 'integer', 'between:1,20'],
            "{$prefix}.reps" => ['nullable', 'integer', 'between:1,200'],
            "{$prefix}.weight" => ['nullable', 'numeric', 'between:-200,999'],
            "{$prefix}.rest" => ['nullable', 'integer', 'between:0,900'],
            "{$prefix}.seconds" => ['nullable', 'integer', 'between:1,3600'],
            "{$prefix}.minutes" => ['nullable', 'integer', 'between:1,600'],
            "{$prefix}.metric" => ['nullable', Rule::enum(CardioMetric::class)],
            "{$prefix}.metric_value" => ['nullable', 'numeric', 'between:0,99.9'],
            "{$prefix}.notes" => ['nullable', 'string', 'max:500'],
        ];
    }

    protected function targetAttributes(string $prefix): array
    {
        return [
            "{$prefix}.sets" => 'sets', "{$prefix}.reps" => 'herhalingen', "{$prefix}.weight" => 'gewicht', "{$prefix}.rest" => 'rust',
            "{$prefix}.seconds" => 'tijd', "{$prefix}.minutes" => 'tijd', "{$prefix}.metric" => 'instelling',
            "{$prefix}.metric_value" => 'waarde', "{$prefix}.notes" => 'notitie',
        ];
    }

    /** Komma's als decimaalteken toestaan (22,5 kg) */
    protected function normalizeDecimals(array $item): array
    {
        foreach (['weight', 'metric_value'] as $field) {
            if (is_string($item[$field] ?? null)) {
                $item[$field] = str_replace(',', '.', trim($item[$field]));
            }
        }

        return $item;
    }

    /** Doelvelden van een item naar de database-kolommen; alleen wat bij het type hoort */
    protected function targets(ExerciseType $type, array $item): array
    {
        $cardio = $type === ExerciseType::Cardio;

        return [
            'target_sets' => $type->usesSets() ? $item['sets'] : null,
            'target_reps' => $type->usesReps() ? $item['reps'] : null,
            'target_weight' => ($type->usesWeight() || $type->usesExtraWeight()) && filled($item['weight']) ? $item['weight'] : null,
            'rest_seconds' => $type->usesSets() ? $item['rest'] : null,
            'target_duration_seconds' => match (true) {
                $cardio => $item['minutes'] ? $item['minutes'] * 60 : null,
                $type === ExerciseType::Duration => $item['seconds'],
                default => null,
            },
            'target_metric' => $cardio ? ($item['metric'] ?: null) : null,
            'target_metric_value' => $cardio && filled($item['metric_value']) ? $item['metric_value'] : null,
            'notes' => filled($item['notes']) ? $item['notes'] : null,
        ];
    }

    /** Een item voor het formulier, uit de doel-kolommen */
    protected function toItem(Exercise $exercise, array $targets, ?int $id = null): array
    {
        $seconds = $targets['target_duration_seconds'] ?? null;
        $cardio = $exercise->type === ExerciseType::Cardio;
        $weight = $targets['target_weight'] ?? null;
        $metric = $targets['target_metric'] ?? null;
        $metricValue = $targets['target_metric_value'] ?? null;

        return [
            'key' => (string) Str::uuid(),
            'id' => $id,
            'exercise_id' => $exercise->id,
            'sets' => $targets['target_sets'] ?? null,
            'reps' => $targets['target_reps'] ?? null,
            'weight' => filled($weight) ? rtrim(rtrim((string) $weight, '0'), '.') : null,
            'rest' => $targets['rest_seconds'] ?? null,
            'seconds' => $cardio ? null : $seconds,
            'minutes' => $cardio && $seconds ? intdiv($seconds, 60) : null,
            'metric' => $metric instanceof CardioMetric ? $metric->value : $metric,
            'metric_value' => filled($metricValue) ? rtrim(rtrim((string) $metricValue, '0'), '.') : null,
            'notes' => $targets['notes'] ?? null,
        ];
    }
}
