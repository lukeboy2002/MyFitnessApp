<?php

namespace App\Enums;

enum Equipment: string
{
    case None = 'none';
    case Machine = 'machine';
    case Cable = 'cable';
    case Plate = 'plate';
    case Barbell = 'barbell';
    case Dumbbell = 'dumbbell';
    case Kettlebell = 'kettlebell';
    case Band = 'band';
    case Suspension = 'suspension';
    case CardioMachine = 'cardio_machine';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::None => 'Geen (eigen gewicht)',
            self::Machine => 'Machine',
            self::Cable => 'Kabel',
            self::Plate => 'Plate-loaded',
            self::Barbell => 'Barbell',
            self::Dumbbell => 'Dumbbell',
            self::Kettlebell => 'Kettlebell',
            self::Band => 'Weerstandsband',
            self::Suspension => 'Suspension (TRX)',
            self::CardioMachine => 'Cardio-apparaat',
            self::Other => 'Overig',
        };
    }

    /** ['machine' => 'Machine', ...] voor <x-form.select> */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $e) => [$e->value => $e->label()])->all();
    }
}
