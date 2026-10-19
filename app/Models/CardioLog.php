<?php

namespace App\Models;

use App\Enums\CardioMetric;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'session_exercise_id', 'duration_seconds', 'distance_m', 'metric', 'metric_value', 'avg_watts', 'mets',
    'active_kcal', 'total_kcal', 'avg_heart_rate', 'cadence', 'incline', 'avg_act_hr',
    'rotations', 'floors', 'stroke_rate', 'pace_seconds', 'extra_metrics', 'source',
])]
class CardioLog extends Model
{
    /** Velden op het formulier: kolom => [label, eenheid] */
    public const FIELDS = [
        'avg_watts' => ['Gem. watts', 'W'],
        'mets' => ['METs', null],
        'active_kcal' => ['ACT', 'kcal'],
        'total_kcal' => ['TTL', 'kcal'],
        'avg_act_hr' => ['AVG ACT/HR', 'kcal'],
        'avg_heart_rate' => ['Gem. hartslag', 'bpm'],
        'cadence' => ['Cadans', 'rpm'],
        'incline' => ['Helling', '%'],
    ];

    protected function casts(): array
    {
        return [
            'metric' => CardioMetric::class,
            'metric_value' => 'decimal:1',
            'mets' => 'decimal:1',
            'incline' => 'decimal:1',
            'extra_metrics' => 'array',
        ];
    }

    public function sessionExercise(): BelongsTo
    {
        return $this->belongsTo(SessionExercise::class);
    }

    public function splits(): HasMany
    {
        return $this->hasMany(CardioSplit::class)->orderBy('number');
    }

    /** Tussentijden: [km => seconden] */
    public function kmTimes(): array
    {
        return $this->splits->pluck('duration_seconds', 'number')->all();
    }

    /** Snelheid in km/h bij een km-tijd: 660 sec → "5,5" */
    public static function speed(?int $seconds): ?string
    {
        return $seconds ? WorkoutSession::formatNumber(round(3600 / $seconds, 1)) : null;
    }

    /** Gemiddelde snelheid in km/h uit tijd en afstand: 30 min, 12 km → "24" */
    public function averageSpeed(): ?string
    {
        return $this->duration_seconds && $this->distance_m
            ? WorkoutSession::formatNumber(round($this->distance_m / 1000 / ($this->duration_seconds / 3600), 1))
            : null;
    }

    /** 332 → "5:32" */
    public static function pace(?int $seconds): ?string
    {
        return $seconds === null ? null : sprintf('%d:%02d', intdiv($seconds, 60), $seconds % 60);
    }
}
