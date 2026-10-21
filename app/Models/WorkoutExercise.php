<?php

namespace App\Models;

use App\Enums\ExerciseType;
use App\Models\Concerns\HasTargets;
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
    use HasFactory, HasTargets;

    /** Rusttijd tussen sets als je niets invult */
    public const DEFAULT_REST_SECONDS = 90;

    public function workout(): BelongsTo
    {
        return $this->belongsTo(Workout::class);
    }

    public function exercise(): BelongsTo
    {
        return $this->belongsTo(Exercise::class);
    }

    /** Rust tussen sets voor een nieuwe regel; een admin kan dit wijzigen bij Instellingen */
    public static function defaultRestSeconds(): int
    {
        return (int) Setting::get('default_rest_seconds', self::DEFAULT_REST_SECONDS);
    }

    /** Doelen voor een nieuwe regel, overgenomen van het standaarddoel van de oefening */
    public static function defaultsFor(Exercise $exercise): array
    {
        $type = $exercise->type;

        return [
            'target_sets' => $type->usesSets() ? $exercise->default_sets : null,
            'target_reps' => $type->usesReps() ? $exercise->default_reps : null,
            'target_weight' => $type->usesWeight() || $type->usesExtraWeight() ? $exercise->default_weight : null,
            'rest_seconds' => $type->usesSets() ? self::defaultRestSeconds() : null,
            'target_duration_seconds' => $type->usesDuration() ? $exercise->default_duration_seconds : null,
            'target_metric' => $type->usesMetric() ? $exercise->default_metric : null,
            'target_metric_value' => $type->usesMetric() ? $exercise->default_metric_value : null,
        ];
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
        $rest = $this->rest_seconds ?? self::defaultRestSeconds();

        return $sets * $work + ($sets - 1) * $rest + 90;
    }
}
