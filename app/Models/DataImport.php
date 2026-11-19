<?php

namespace App\Models;

use App\Support\Import\WorkoutFile;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Een import uit een andere app; ongedaan maken = verwijderen (de trainingen gaan mee) */
#[Fillable(['user_id', 'app', 'filename', 'sessions_count', 'skipped_count', 'exercise_map'])]
class DataImport extends Model
{
    protected $attributes = [
        'sessions_count' => 0,
        'skipped_count' => 0,
        'exercise_map' => null,
    ];

    protected function casts(): array
    {
        return [
            'exercise_map' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(WorkoutSession::class);
    }

    /** Naam van de app, bv. "Hevy" */
    public function appLabel(): string
    {
        return WorkoutFile::APPS[$this->app] ?? ucfirst($this->app);
    }
}
