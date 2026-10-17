<?php

namespace App\Models;

use App\Models\Concerns\HasTargets;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable([
    'workout_session_id', 'exercise_id', 'workout_exercise_id', 'position', 'target_sets', 'target_reps', 'target_weight',
    'rest_seconds', 'target_duration_seconds', 'target_metric', 'target_metric_value', 'completed_at', 'notes',
])]
class SessionExercise extends Model
{
    use HasTargets;

    protected function casts(): array
    {
        return [
            'completed_at' => 'datetime',
        ];
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(WorkoutSession::class, 'workout_session_id');
    }

    public function exercise(): BelongsTo
    {
        return $this->belongsTo(Exercise::class);
    }

    public function workoutExercise(): BelongsTo
    {
        return $this->belongsTo(WorkoutExercise::class);
    }

    public function sets(): HasMany
    {
        return $this->hasMany(SetLog::class)->orderBy('set_number');
    }

    public function cardio(): HasOne
    {
        return $this->hasOne(CardioLog::class);
    }

    /** Klaar: alle sets afgevinkt, of (cardio) afgerond */
    public function isDone(): bool
    {
        return $this->exercise->type->usesSets()
            ? $this->sets->isNotEmpty() && $this->sets->every('is_completed')
            : $this->completed_at !== null;
    }
}
