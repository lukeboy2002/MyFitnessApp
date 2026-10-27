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
            self::Beginner => 'Beginner (korter dan een jaar)',
            self::Intermediate => 'Gemiddeld (1 tot 3 jaar)',
            self::Advanced => 'Gevorderd (langer dan 3 jaar)',
        };
    }

    /** ['beginner' => 'Beginner …', ...] voor <x-form.select> */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $level) => [$level->value => $level->label()])->all();
    }
}
