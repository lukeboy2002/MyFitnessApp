<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Lichaamsmaten van één dag, in cm; elke maat is optioneel */
#[Fillable(['measured_on', 'chest_cm', 'waist_cm', 'hips_cm', 'arm_cm', 'thigh_cm', 'calf_cm'])]
class BodyMeasurement extends Model
{
    /** kolom => label */
    /** kolom => Engelse naam (vertaald met labels() of __()) */
    public const FIELDS = [
        'chest_cm' => 'Chest',
        'waist_cm' => 'Waist',
        'hips_cm' => 'Hips',
        'arm_cm' => 'Upper arm',
        'thigh_cm' => 'Thigh',
        'calf_cm' => 'Calf',
    ];

    /** @return array<string, string> kolom => naam in de taal van de app */
    public static function labels(): array
    {
        return array_map(fn (string $label) => __($label), self::FIELDS);
    }

    /** De naam midden in een zin: klein in het Nederlands en Engels, met hoofdletter in het Duits */
    public static function inSentence(string $field): string
    {
        $label = __(self::FIELDS[$field]);

        return app()->getLocale() === 'de' ? $label : mb_strtolower($label);
    }

    protected function casts(): array
    {
        return [
            'measured_on' => 'date',
            ...array_fill_keys(array_keys(self::FIELDS), 'decimal:1'),
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
