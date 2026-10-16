<?php

namespace App\Models;

use App\Enums\CardioMetric;
use App\Enums\ExerciseType;
use Database\Factories\WorkoutExerciseFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'workout_id', 'exercise_id', 'position', 'target_sets', 'target_reps', 'target_weight', 'rest_seconds',
    'target_duration_seconds', 'target_metric', 'target_metric_value', 'notes',
])]
class WorkoutExercise extends Model
{
    /** @use HasFactory<WorkoutExerciseFactory> */
    use HasFactory;

    /** Rusttijd tussen sets als je niets invult */
    public const DEFAULT_REST_SECONDS = 90;

    protected function casts(): array
    {
        return [
            'target_weight' => 'decimal:2',
            'target_metric' => CardioMetric::class,
            'target_metric_value' => 'decimal:1',
        ];
    }

    public function workout(): BelongsTo
    {
        return $this->belongsTo(Workout::class);
    }

    public function exercise(): BelongsTo
    {
        return $this->belongsTo(Exercise::class);
    }

    /** Doelen voor een nieuwe regel, overgenomen van het standaarddoel van de oefening */
    public static function defaultsFor(Exercise $exercise): array
    {
        $type = $exercise->type;

        return [
            'target_sets' => $type->usesSets() ? $exercise->default_sets : null,
            'target_reps' => $type->usesReps() ? $exercise->default_reps : null,
            'target_weight' => $type->usesWeight() || $type->usesExtraWeight() ? $exercise->default_weight : null,
            'rest_seconds' => $type->usesSets() ? self::DEFAULT_REST_SECONDS : null,
            'target_duration_seconds' => $type->usesDuration() ? $exercise->default_duration_seconds : null,
            'target_metric' => $type->usesMetric() ? $exercise->default_metric : null,
            'target_metric_value' => $type->usesMetric() ? $exercise->default_metric_value : null,
        ];
    }

    /** Doel als tekst, bv. "3 × 10 · 40 kg" of "20 min · weerstand 8" */
    public function targetSummary(): ?string
    {
        $type = $this->exercise->type;
        $parts = [];

        if ($type->usesSets() && $this->target_sets) {
            $parts[] = $this->target_sets.' ×'.($type->usesReps() && $this->target_reps ? ' '.$this->target_reps : '');
        }

        if (($type->usesWeight() || $type->usesExtraWeight()) && (float) $this->target_weight !== 0.0) {
            $kg = static::number($this->target_weight).' kg';
            $parts[] = $type->usesExtraWeight() && $this->target_weight > 0 ? '+'.$kg : $kg;
        }

        if ($type->usesDuration() && $this->target_duration_seconds) {
            $parts[] = static::duration($this->target_duration_seconds);
        }

        if ($type->usesMetric() && $this->target_metric && filled($this->target_metric_value)) {
            $parts[] = $this->target_metric->format($this->target_metric_value);
        }

        return $parts ? implode(' · ', $parts) : null;
    }

    /** Ruwe schatting van de tijd die deze oefening kost, inclusief rust en wisselen */
    public function estimatedSeconds(): int
    {
        $type = $this->exercise->type;

        if ($type === ExerciseType::Cardio) {
            return ($this->target_duration_seconds ?: 20 * 60) + 60;
        }

        $sets = $this->target_sets ?: 3;
        $work = $type === ExerciseType::Duration ? ($this->target_duration_seconds ?: 30) : ($this->target_reps ?: 10) * 4;
        $rest = $this->rest_seconds ?? self::DEFAULT_REST_SECONDS;

        return $sets * $work + ($sets - 1) * $rest + 90;
    }

    public static function duration(int $seconds): string
    {
        return $seconds % 60 === 0 ? ($seconds / 60).' min' : ($seconds >= 60 ? intdiv($seconds, 60).':'.str_pad($seconds % 60, 2, '0', STR_PAD_LEFT).' min' : $seconds.' sec');
    }

    private static function number(float|string $value): string
    {
        return str_replace('.', ',', rtrim(rtrim(number_format((float) $value, 2, '.', ''), '0'), '.'));
    }
}
