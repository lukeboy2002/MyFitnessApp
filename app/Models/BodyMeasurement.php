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
    public const FIELDS = [
        'chest_cm' => 'Borst',
        'waist_cm' => 'Taille',
        'hips_cm' => 'Heupen',
        'arm_cm' => 'Bovenarm',
        'thigh_cm' => 'Bovenbeen',
        'calf_cm' => 'Kuit',
    ];

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
