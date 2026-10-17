<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Een workout die op een dag is ingepland; workout_session_id wordt gevuld zodra je hem doet */
#[Fillable(['user_id', 'workout_id', 'scheduled_on', 'workout_session_id', 'notes'])]
class ScheduledWorkout extends Model
{
    protected function casts(): array
    {
        return [
            'scheduled_on' => 'date',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function workout(): BelongsTo
    {
        return $this->belongsTo(Workout::class);
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(WorkoutSession::class, 'workout_session_id');
    }

    /** Nog niet gedaan */
    #[Scope]
    protected function open(Builder $query): void
    {
        $query->whereNull('workout_session_id');
    }

    public function isDone(): bool
    {
        return $this->workout_session_id !== null;
    }

    /** Gepland in het verleden en niet gedaan */
    public function isMissed(): bool
    {
        return ! $this->isDone() && $this->scheduled_on->isBefore(today());
    }
}
