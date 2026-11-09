<?php

namespace App\Support;

use App\Enums\ExerciseType;
use App\Enums\SessionStatus;
use App\Models\BodyMeasurement;
use App\Models\CardioLog;
use App\Models\SessionExercise;
use App\Models\User;
use Generator;

/**
 * Eigen gegevens als CSV voor Excel/Numbers (Nederlandse instellingen: puntkomma, decimale komma).
 * Trainingen: één regel per set, cardio één regel per oefening.
 */
class DataExport
{
    /** type in de url => titel */
    public const TYPES = [
        'trainingen' => 'Trainingen',
        'wegingen' => 'Wegingen',
        'maten' => 'Lichaamsmaten',
    ];

    public static function count(User $user, string $type): int
    {
        return match ($type) {
            'trainingen' => $user->workoutSessions()->where('status', SessionStatus::Completed)->count(),
            'wegingen' => $user->bodyWeights()->count(),
            'maten' => $user->bodyMeasurements()->count(),
        };
    }

    public static function headers(string $type): array
    {
        return match ($type) {
            'trainingen' => [
                'Datum', 'Starttijd', 'Training', 'Duur training (min)', 'Oefening', 'Type',
                'Set', 'Herhalingen', 'Gewicht (kg)', 'Tijd', 'Afgerond',
                'Afstand (km)', 'Cardiotijd', 'Instelling', 'Waarde',
                ...array_column(CardioLog::FIELDS, 0),
                'Notitie',
            ],
            'wegingen' => ['Datum', 'Gewicht (kg)', 'Vetpercentage (%)'],
            'maten' => ['Datum', ...array_map(fn (string $label) => "{$label} (cm)", BodyMeasurement::FIELDS)],
        };
    }

    public static function rows(User $user, string $type): Generator
    {
        return match ($type) {
            'trainingen' => self::sessionRows($user),
            'wegingen' => self::weightRows($user),
            'maten' => self::measurementRows($user),
        };
    }

    private static function sessionRows(User $user): Generator
    {
        $sessions = $user->workoutSessions()
            ->where('status', SessionStatus::Completed)
            ->with(['exercises' => fn ($q) => $q->orderBy('position'), 'exercises.exercise', 'exercises.sets', 'exercises.cardio'])
            ->orderBy('started_at')
            ->lazy(50);

        foreach ($sessions as $session) {
            $base = [
                $session->started_at->format('d-m-Y'),
                $session->started_at->format('H:i'),
                $session->name,
                $session->duration_seconds === null ? '' : (string) round($session->duration_seconds / 60),
            ];
            $empty = true;

            foreach ($session->exercises as $item) {
                foreach (self::exerciseRows($item) as $row) {
                    $empty = false;

                    yield [...$base, $item->exercise->name, $item->exercise->type->label(), ...$row, $session->notes];
                }
            }

            if ($empty) {
                yield [...$base, ...array_fill(0, count(self::headers('trainingen')) - 5, ''), $session->notes];
            }
        }
    }

    /** Kolommen vanaf "Set" t/m de cardiovelden */
    private static function exerciseRows(SessionExercise $item): Generator
    {
        $noCardio = array_fill(0, 4 + count(CardioLog::FIELDS), '');

        if ($item->exercise->type === ExerciseType::Cardio) {
            $cardio = $item->cardio;

            if ($cardio) {
                yield [
                    '', '', '', '', $item->completed_at ? 'ja' : 'nee',
                    self::number($cardio->distance_m === null ? null : $cardio->distance_m / 1000, 2),
                    self::duration($cardio->duration_seconds),
                    $cardio->metric?->label() ?? '',
                    self::number($cardio->metric_value, 1),
                    ...array_map(fn (string $field) => self::number($cardio->{$field}, 1), array_keys(CardioLog::FIELDS)),
                ];
            }

            return;
        }

        foreach ($item->sets->sortBy('set_number') as $set) {
            yield [
                $set->set_number,
                $set->reps ?? '',
                $item->exercise->type->usesWeight() || $set->weight !== null ? self::number($set->weight, 2) : '',
                self::duration($set->duration_seconds),
                $set->is_completed ? 'ja' : 'nee',
                ...$noCardio,
            ];
        }
    }

    private static function weightRows(User $user): Generator
    {
        foreach ($user->bodyWeights()->orderBy('measured_on')->lazy() as $weight) {
            yield [$weight->measured_on->format('d-m-Y'), self::number($weight->weight_kg, 1), self::number($weight->body_fat, 1)];
        }
    }

    private static function measurementRows(User $user): Generator
    {
        foreach ($user->bodyMeasurements()->orderBy('measured_on')->lazy() as $measurement) {
            yield [
                $measurement->measured_on->format('d-m-Y'),
                ...array_map(fn (string $field) => self::number($measurement->{$field}, 1), array_keys(BodyMeasurement::FIELDS)),
            ];
        }
    }

    /** 82.50 => "82,5", null => "" */
    public static function number(float|int|string|null $value, int $decimals): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        $formatted = number_format((float) $value, $decimals, ',', '');

        return str_contains($formatted, ',') ? rtrim(rtrim($formatted, '0'), ',') : $formatted;
    }

    /** 754 => "12:34", 3723 => "1:02:03" */
    public static function duration(?int $seconds): string
    {
        if ($seconds === null) {
            return '';
        }

        return $seconds >= 3600
            ? sprintf('%d:%02d:%02d', intdiv($seconds, 3600), intdiv($seconds % 3600, 60), $seconds % 60)
            : sprintf('%d:%02d', intdiv($seconds, 60), $seconds % 60);
    }
}
