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
            self::LoseWeight => 'Afvallen',
            self::BuildMuscle => 'Spiermassa opbouwen',
            self::Strength => 'Sterker worden',
            self::Endurance => 'Betere conditie',
            self::StayFit => 'Fit blijven',
        };
    }

    /** ['lose_weight' => 'Afvallen', ...] voor <x-form.select> */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $goal) => [$goal->value => $goal->label()])->all();
    }
}
