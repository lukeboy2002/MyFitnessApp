<?php

namespace App\Enums;

use App\Support\Locale;

/** De instelling op een cardio-apparaat waar je op traint, naast de tijd */
enum CardioMetric: string
{
    case Resistance = 'resistance';
    case Level = 'level';
    case Speed = 'speed';

    public function label(): string
    {
        return match ($this) {
            self::Resistance => __('Resistance'),
            self::Level => __('Level'),
            self::Speed => __('Speed'),
        };
    }

    public function unit(): ?string
    {
        return $this === self::Speed ? 'km/h' : null;
    }

    /** Waarde met label, bv. "weerstand 8", "niveau 12" of "10,5 km/h" */
    public function format(float|string $value): string
    {
        $number = str_replace('.', Locale::decimalSeparator(), rtrim(rtrim(number_format((float) $value, 1, '.', ''), '0'), '.'));

        return $this === self::Speed ? "{$number} km/h" : (app()->getLocale() === 'de' ? $this->label() : mb_strtolower($this->label())).' '.$number;
    }

    /** ['resistance' => 'Weerstand', ...] voor <x-form.select> */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $metric) => [$metric->value => $metric->label()])->all();
    }
}
