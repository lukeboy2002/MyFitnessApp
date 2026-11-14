<?php

namespace App\Enums;

/** Waar je vooral voor traint */
enum FitnessGoal: string
{
    case LoseWeight = 'lose_weight';
    case BuildMuscle = 'build_muscle';
    case Strength = 'strength';
    case Endurance = 'endurance';
    case StayFit = 'stay_fit';

    public function label(): string
    {
        return match ($this) {
            self::LoseWeight => __('Lose weight'),
            self::BuildMuscle => __('Build muscle'),
            self::Strength => __('Get stronger'),
            self::Endurance => __('Better endurance'),
            self::StayFit => __('Stay fit'),
        };
    }

    /** ['lose_weight' => 'Afvallen', ...] voor <x-form.select> */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $goal) => [$goal->value => $goal->label()])->all();
    }
}
