<?php

namespace App\Models;

use App\Models\Concerns\OwnedOrGlobal;
use Database\Factories\WorkoutFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Arr;

#[Fillable(['user_id', 'name', 'slug', 'description', 'color', 'estimated_minutes', 'archived_at'])]
class Workout extends Model
{
    /** @use HasFactory<WorkoutFactory> */
    use HasFactory, OwnedOrGlobal;

    public const COLORS = ['#1d63c5', '#00468c', '#0e8ad6', '#0e9f8a', '#65a30d', '#d4a106', '#f97316', '#e5650f', '#db3f6b', '#8b5cf6', '#64748b'];

    protected function casts(): array
    {
        return [
            'archived_at' => 'datetime',
        ];
    }

    /** De oefeningen met hun doelen, in volgorde */
    public function exercises(): HasMany
    {
        return $this->hasMany(WorkoutExercise::class)->orderBy('position');
    }

    public function muscleGroups(): BelongsToMany
    {
        return $this->belongsToMany(MuscleGroup::class)->orderBy('muscle_groups.sort_order')->orderBy('muscle_groups.name');
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

    /** Zelf ingevulde duur, anders een schatting op basis van de oefeningen */
    public function minutes(): ?int
    {
        if ($this->estimated_minutes) {
            return $this->estimated_minutes;
        }

        $seconds = $this->exercises->sum(fn (WorkoutExercise $item) => $item->estimatedSeconds());

        return $seconds ? max(5, (int) round($seconds / 60 / 5) * 5) : null;
    }

    /** Kopie met dezelfde oefeningen, doelen en spiergroepen */
    public function duplicate(string $name): self
    {
        $copy = $this->replicate(['slug', 'archived_at']);
        $copy->name = $name;
        $copy->slug = static::uniqueSlug($name, $this->user_id);
        $copy->save();

        $copy->muscleGroups()->sync($this->muscleGroups()->pluck('muscle_groups.id'));
        $this->exercises->each(fn (WorkoutExercise $item) => $copy->exercises()->create(Arr::except($item->only($item->getFillable()), 'workout_id')));

        return $copy;
    }
}
