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
            self::Sessions => __('Number of trainings'),
            self::Hours => __('Total training time'),
            self::Volume => __('Total volume (reps × kg)'),
            self::Distance => __('Total cardio distance'),
            self::Records => __('Improved records'),
            self::Streak => __('Weeks in a row with 2+ trainings'),
            self::FullWeek => __('Trainings in one week'),
            self::SessionVolume => __('Volume in one training'),
            self::SessionDistance => __('Cardio distance in one training'),
            self::LongSessions => __('Trainings of 1.5 hours or longer'),
            self::Early => __('Trainings before 7:00'),
            self::Late => __('Trainings after 21:00'),
            self::Weekend => __('Trainings at the weekend'),
            self::ExerciseCount => __('Times an exercise was done'),
            self::ExerciseWeight => __('Heaviest weight on an exercise'),
            self::ExerciseDistance => __('Total distance on a cardio exercise'),
        };
    }

    /** Voor de voortgang, bv. "37 / 50 trainingen" */
    public function unit(): string
    {
        return match ($this) {
            self::Hours => __('hours'),
            self::Volume, self::SessionVolume, self::ExerciseWeight => 'kg',
            self::Distance, self::SessionDistance, self::ExerciseDistance => 'km',
            self::Records => __('records'),
            self::Streak => __('weeks in a row'),
            self::ExerciseCount => __('times'),
            default => __('trainings'),
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
