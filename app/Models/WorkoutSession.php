<?php

namespace App\Models;

use App\Enums\SessionStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

#[Fillable([
    'user_id', 'workout_id', 'name', 'color', 'status', 'started_at', 'paused_at', 'paused_seconds',
    'ended_at', 'duration_seconds', 'source', 'external_id', 'notes',
])]
class WorkoutSession extends Model
{
    /** Quickstart: een vrije training, altijd met deze naam, kleur en omschrijving */
    public const QUICKSTART = 'quickstart';

    public const QUICKSTART_NAME = 'Free Training';

    public const QUICKSTART_COLOR = '#c026d3';

    public const QUICKSTART_DESCRIPTION = 'Dit is een vrije training. Deze kan iedere keer anders zijn.';

    protected function casts(): array
    {
        return [
            'status' => SessionStatus::class,
            'started_at' => 'datetime',
            'paused_at' => 'datetime',
            'ended_at' => 'datetime',
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

    public function exercises(): HasMany
    {
        return $this->hasMany(SessionExercise::class)->orderBy('position');
    }

    public function sets(): HasManyThrough
    {
        return $this->hasManyThrough(SetLog::class, SessionExercise::class);
    }

    /** Trainingen die nog lopen (ook gepauzeerd) */
    #[Scope]
    protected function running(Builder $query): void
    {
        $query->whereIn('status', [SessionStatus::Active, SessionStatus::Paused]);
    }

    public function isQuickstart(): bool
    {
        return $this->source === self::QUICKSTART;
    }

    public function isRunning(): bool
    {
        return $this->status->isRunning();
    }

    public function isPaused(): bool
    {
        return $this->status === SessionStatus::Paused;
    }

    /** Trainingstijd zonder pauzes; loopt door zolang de training bezig is */
    public function elapsedSeconds(): int
    {
        if ($this->duration_seconds !== null) {
            return $this->duration_seconds;
        }

        $until = $this->paused_at ?? $this->ended_at ?? now();

        return max(0, (int) $this->started_at->diffInSeconds($until) - $this->paused_seconds);
    }

    public function pause(): void
    {
        if ($this->status === SessionStatus::Active) {
            $this->update(['status' => SessionStatus::Paused, 'paused_at' => now()]);
        }
    }

    public function resume(): void
    {
        if ($this->isPaused()) {
            $this->update([
                'status' => SessionStatus::Active,
                'paused_seconds' => $this->paused_seconds + (int) $this->paused_at->diffInSeconds(now()),
                'paused_at' => null,
            ]);
        }
    }

    /** Timer stoppen en de duur vastleggen */
    public function finish(): void
    {
        $this->resume();
        $this->ended_at = now();
        $this->duration_seconds = $this->elapsedSeconds();
        $this->status = SessionStatus::Completed;
        $this->save();
    }

    /** "1:05:09" of "23:41" */
    public static function clock(int $seconds): string
    {
        $time = sprintf('%02d:%02d', intdiv($seconds % 3600, 60), $seconds % 60);

        return $seconds >= 3600 ? intdiv($seconds, 3600).':'.$time : $time;
    }

    /** Totaal volume van de afgevinkte krachtsets: Σ herhalingen × kg */
    public function volume(): float
    {
        return (float) $this->exercises
            ->filter(fn (SessionExercise $item) => $item->exercise->type->usesWeight())
            ->flatMap->sets
            ->where('is_completed', true)
            ->sum(fn (SetLog $set) => (int) $set->reps * (float) $set->weight);
    }

    public static function formatNumber(float|string|null $value): string
    {
        return WorkoutExercise::number($value);
    }
}
