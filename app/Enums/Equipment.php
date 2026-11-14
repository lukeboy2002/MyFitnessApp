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
            self::None => __('None (bodyweight)'),
            self::Machine => __('Machine'),
            self::Cable => __('Cable'),
            self::Plate => __(__('Plate-loaded')),
            self::Barbell => __('Barbell'),
            self::Dumbbell => __('Dumbbell'),
            self::Kettlebell => __('Kettlebell'),
            self::Band => __('Resistance band'),
            self::Suspension => __(__('Suspension (TRX)')),
            self::CardioMachine => __('Cardio machine'),
            self::Other => __('Other equipment'),
        };
    }

    /** ['machine' => 'Machine', ...] voor <x-form.select> */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $e) => [$e->value => $e->label()])->all();
    }
}
