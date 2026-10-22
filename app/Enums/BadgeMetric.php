<?php

namespace App\Enums;

use App\Models\WorkoutSession;
use App\Support\TrainingStats;

/** Wat een badge meet; App\Support\Achievements rekent de waarden uit, training na training */
enum BadgeMetric: string
{
    case Sessions = 'sessions';
    case Hours = 'hours';
    case Volume = 'volume';
    case Distance = 'distance';
    case Records = 'records';
    case Streak = 'streak';
    case FullWeek = 'full_week';
    case SessionVolume = 'session_volume';
    case SessionDistance = 'session_distance';
    case LongSessions = 'long_sessions';
    case Early = 'early';
    case Late = 'late';
    case Weekend = 'weekend';
    case ExerciseCount = 'exercise_count';
    case ExerciseWeight = 'exercise_weight';
    case ExerciseDistance = 'exercise_distance';

    public function label(): string
    {
        return match ($this) {
            self::Sessions => 'Aantal trainingen',
            self::Hours => 'Trainingstijd totaal',
            self::Volume => 'Volume totaal (reps × kg)',
            self::Distance => 'Cardio-afstand totaal',
            self::Records => 'Verbeterde records',
            self::Streak => 'Weken op rij met 2+ trainingen',
            self::FullWeek => 'Trainingen in één week',
            self::SessionVolume => 'Volume in één training',
            self::SessionDistance => 'Cardio-afstand in één training',
            self::LongSessions => 'Trainingen van 1,5 uur of langer',
            self::Early => 'Trainingen vóór 7:00',
            self::Late => 'Trainingen na 21:00',
            self::Weekend => 'Trainingen in het weekend',
            self::ExerciseCount => 'Keer een oefening gedaan',
            self::ExerciseWeight => 'Zwaarste gewicht bij een oefening',
            self::ExerciseDistance => 'Afstand totaal bij een cardio-oefening',
        };
    }

    /** Voor de voortgang, bv. "37 / 50 trainingen" */
    public function unit(): string
    {
        return match ($this) {
            self::Hours => 'uur',
            self::Volume, self::SessionVolume, self::ExerciseWeight => 'kg',
            self::Distance, self::SessionDistance, self::ExerciseDistance => 'km',
            self::Records => 'records',
            self::Streak => 'weken op rij',
            self::ExerciseCount => 'keer',
            default => 'trainingen',
        };
    }

    /** Hoort bij één oefening (kiezen in het formulier) */
    public function needsExercise(): bool
    {
        return in_array($this, [self::ExerciseCount, self::ExerciseWeight, self::ExerciseDistance]);
    }

    /** Getal zoals op het scherm: kilo's met punten (12.340), de rest met hooguit 1 decimaal (94,6) */
    public function format(float|int $value): string
    {
        return in_array($this, [self::Volume, self::SessionVolume])
            ? TrainingStats::kilos($value)
            : WorkoutSession::formatNumber(round($value, 1));
    }

    /** @return array<string, string> voor een select */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $metric) => [$metric->value => $metric->label()])->all();
    }
}
