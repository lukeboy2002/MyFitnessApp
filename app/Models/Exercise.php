<?php

namespace App\Models;

use App\Enums\CardioMetric;
use App\Enums\Equipment;
use App\Enums\ExerciseType;
use App\Models\Concerns\OwnedOrGlobal;
use Database\Factories\ExerciseFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

#[Fillable([
    'user_id', 'name', 'slug', 'type', 'equipment', 'description', 'instructions', 'tips', 'image_path',
    'default_sets', 'default_reps', 'default_weight', 'default_duration_seconds', 'default_metric', 'default_metric_value', 'archived_at',
])]
class Exercise extends Model
{
    /** @use HasFactory<ExerciseFactory> */
    use HasFactory, OwnedOrGlobal;

    protected function casts(): array
    {
        return [
            'type' => ExerciseType::class,
            'equipment' => Equipment::class,
            'instructions' => 'array',
            'tips' => 'array',
            'default_weight' => 'decimal:2',
            'default_metric' => CardioMetric::class,
            'default_metric_value' => 'decimal:1',
            'archived_at' => 'datetime',
        ];
    }

    /** Spiergroepen, de primaire eerst */
    public function muscleGroups(): BelongsToMany
    {
        return $this->belongsToMany(MuscleGroup::class)
            ->withPivot('is_primary')
            ->orderByPivot('is_primary', 'desc')
            ->orderBy('muscle_groups.sort_order');
    }

    /** Plekken in workouts waar deze oefening in zit */
    public function workoutExercises(): HasMany
    {
        return $this->hasMany(WorkoutExercise::class);
    }

    public function primaryMuscleGroup(): ?MuscleGroup
    {
        return $this->muscleGroups->first(fn (MuscleGroup $group) => $group->pivot->is_primary);
    }

    /** @return Collection<int, MuscleGroup> */
    public function secondaryMuscleGroups(): Collection
    {
        return $this->muscleGroups->reject(fn (MuscleGroup $group) => $group->pivot->is_primary)->values();
    }

    /** Eén primaire en eventueel een paar secundaire spiergroepen */
    public function syncMuscleGroups(int $primaryId, array $secondaryIds = []): void
    {
        $this->muscleGroups()->sync(
            collect($secondaryIds)->map(fn ($id) => (int) $id)->reject(fn ($id) => $id === $primaryId)
                ->mapWithKeys(fn ($id) => [$id => ['is_primary' => false]])
                ->put($primaryId, ['is_primary' => true])
                ->all()
        );
        $this->unsetRelation('muscleGroups');
    }

    /** Geüpload plaatje, anders het plaatje van de primaire spiergroep */
    public function imageUrl(): ?string
    {
        return $this->image_path
            ? Storage::disk('public')->url($this->image_path)
            : $this->primaryMuscleGroup()?->imageUrl();
    }

    public function isArchived(): bool
    {
        return $this->archived_at !== null;
    }

    #[Scope]
    protected function active(Builder $query): void
    {
        $query->whereNull('archived_at');
    }

    #[Scope]
    protected function archived(Builder $query): void
    {
        $query->whereNotNull('archived_at');
    }

    /** Standaarddoel als tekst, bv. "3 × 10 · 20 kg" of "20 min" */
    public function defaultTarget(): ?string
    {
        $parts = [];

        if ($this->type->usesSets() && $this->default_sets) {
            $parts[] = $this->default_sets.' ×'.($this->type->usesReps() && $this->default_reps ? ' '.$this->default_reps : '');
        }

        if (($this->type->usesWeight() || $this->type->usesExtraWeight()) && (float) $this->default_weight !== 0.0) {
            $kg = rtrim(rtrim($this->default_weight, '0'), '.').' kg';
            $parts[] = $this->type->usesExtraWeight() && $this->default_weight > 0 ? '+'.$kg : $kg;
        }

        if ($this->type->usesDuration() && $this->default_duration_seconds) {
            $seconds = $this->default_duration_seconds;
            $parts[] = $seconds % 60 === 0 ? ($seconds / 60).' min' : $seconds.' sec';
        }

        if ($this->type->usesMetric() && $this->default_metric && filled($this->default_metric_value)) {
            $parts[] = $this->default_metric->format($this->default_metric_value);
        }

        return $parts ? implode(' · ', $parts) : null;
    }
}
