<?php

namespace App\Enums;

/**
 * Hoe je een oefening bijhoudt. Bepaalt welke velden je bij een set/doel invult.
 */
enum ExerciseType: string
{
    case Strength = 'strength';     // gewicht × herhalingen
    case Bodyweight = 'bodyweight'; // herhalingen met je eigen gewicht
    case Duration = 'duration';     // volhouden op tijd, bv. plank
    case Cardio = 'cardio';         // tijd, afstand, intensiteit

    public function label(): string
    {
        return match ($this) {
            self::Strength => 'Kracht',
            self::Bodyweight => 'Lichaamsgewicht',
            self::Duration => 'Tijd',
            self::Cardio => 'Cardio',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Strength => 'Sets, herhalingen en gewicht',
            self::Bodyweight => 'Herhalingen, eventueel met extra of assisted gewicht',
            self::Duration => 'Sets op tijd, bv. plank',
            self::Cardio => 'Tijd en weerstand, niveau of snelheid',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Strength => 'dumbbell',
            self::Bodyweight => 'body',
            self::Duration => 'clock',
            self::Cardio => 'run',
        };
    }

    /** Kleur voor <x-badge color="..."> */
    public function color(): string
    {
        return match ($this) {
            self::Strength => 'strength',
            self::Bodyweight => 'brand',
            self::Duration => 'success',
            self::Cardio => 'cardio',
        };
    }

    public function usesSets(): bool
    {
        return $this !== self::Cardio;
    }

    public function usesReps(): bool
    {
        return in_array($this, [self::Strength, self::Bodyweight]);
    }

    public function usesWeight(): bool
    {
        return $this === self::Strength;
    }

    /** Lichaamsgewicht: extra gewicht (+, bv. weighted dips) of hulp (−, bv. assisted pull ups) */
    public function usesExtraWeight(): bool
    {
        return $this === self::Bodyweight;
    }

    public function usesDuration(): bool
    {
        return in_array($this, [self::Duration, self::Cardio]);
    }

    /** Cardio: een instelling (weerstand, niveau of snelheid) met een waarde */
    public function usesMetric(): bool
    {
        return $this === self::Cardio;
    }

    /** Standaarddoel voor een nieuwe oefening van dit type */
    public function defaults(): array
    {
        return match ($this) {
            self::Strength => ['default_sets' => 3, 'default_reps' => 10],
            self::Bodyweight => ['default_sets' => 3, 'default_reps' => 10],
            self::Duration => ['default_sets' => 3, 'default_duration_seconds' => 45],
            self::Cardio => ['default_duration_seconds' => 20 * 60],
        };
    }

    /** ['strength' => ['label' => ..., 'icon' => ..., 'description' => ...], ...] voor <x-form.radio-cards> */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $type) => [
            $type->value => ['label' => $type->label(), 'icon' => $type->icon(), 'description' => $type->description()],
        ])->all();
    }
}
