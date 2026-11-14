<?php

namespace App\Enums;

/** Hoe lang je al traint */
enum ExperienceLevel: string
{
    case Beginner = 'beginner';
    case Intermediate = 'intermediate';
    case Advanced = 'advanced';

    public function label(): string
    {
        return match ($this) {
            self::Beginner => __('Beginner (less than a year)'),
            self::Intermediate => __('Intermediate (1 to 3 years)'),
            self::Advanced => __('Advanced (more than 3 years)'),
        };
    }

    /** ['beginner' => 'Beginner …', ...] voor <x-form.select> */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $level) => [$level->value => $level->label()])->all();
    }
}
