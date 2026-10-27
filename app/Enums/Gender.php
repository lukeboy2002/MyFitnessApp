<?php

namespace App\Enums;

enum Gender: string
{
    case Male = 'male';
    case Female = 'female';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Male => 'Man',
            self::Female => 'Vrouw',
            self::Other => 'Anders',
        };
    }

    /** ['male' => 'Man', ...] voor <x-form.select> */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $gender) => [$gender->value => $gender->label()])->all();
    }
}
