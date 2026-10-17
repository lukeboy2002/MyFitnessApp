<?php

namespace App\Enums;

/** Extra velden die alleen sommige cardio-apparaten tonen, per oefening in te stellen */
enum CardioField: string
{
    case Rotations = 'rotations';
    case Floors = 'floors';
    case StrokeRate = 'stroke_rate';
    case Cadence = 'cadence';
    case Incline = 'incline';
    case Pace = 'pace';
    case Splits = 'splits';

    public function label(): string
    {
        return match ($this) {
            self::Rotations => 'Rotaties',
            self::Floors => 'Floors',
            self::StrokeRate => 'Stroke rate',
            self::Cadence => 'Cadans',
            self::Incline => 'Helling',
            self::Pace => 'Pace',
            self::Splits => 'Tussentijden per km',
        };
    }

    public function unit(): ?string
    {
        return match ($this) {
            self::StrokeRate => 'spm',
            self::Cadence => 'rpm',
            self::Incline => '%',
            default => null,
        };
    }

    /** Kolom in cardio_logs; tussentijden staan in een eigen tabel */
    public function column(): ?string
    {
        return match ($this) {
            self::Pace => 'pace_seconds',
            self::Splits => null,
            default => $this->value,
        };
    }

    /** Voor <x-form.chips> */
    public static function options(): array
    {
        return collect(self::cases())->map(fn (self $field) => ['value' => $field->value, 'label' => $field->label()])->all();
    }
}
