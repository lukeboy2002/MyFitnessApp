<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'session_exercise_id', 'set_number', 'target_reps', 'target_weight', 'target_duration_seconds',
    'reps', 'weight', 'duration_seconds', 'is_completed', 'completed_at', 'rpe',
])]
class SetLog extends Model
{
    protected function casts(): array
    {
        return [
            'target_weight' => 'decimal:2',
            'weight' => 'decimal:2',
            'is_completed' => 'boolean',
            'completed_at' => 'datetime',
        ];
    }

    public function sessionExercise(): BelongsTo
    {
        return $this->belongsTo(SessionExercise::class);
    }
}
