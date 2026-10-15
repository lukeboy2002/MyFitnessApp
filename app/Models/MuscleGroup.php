<?php

namespace App\Models;

use App\Models\Concerns\OwnedOrGlobal;
use Database\Factories\MuscleGroupFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['body_part_id', 'user_id', 'name', 'slug', 'color', 'icon', 'sort_order'])]
class MuscleGroup extends Model
{
    /** @use HasFactory<MuscleGroupFactory> */
    use HasFactory, OwnedOrGlobal;

    public function bodyPart(): BelongsTo
    {
        return $this->belongsTo(BodyPart::class);
    }

    public function muscles(): HasMany
    {
        return $this->hasMany(Muscle::class)->orderBy('sort_order')->orderBy('name');
    }

    // Later (stap 4/5):
    // public function exercises(): BelongsToMany { return $this->belongsToMany(Exercise::class)->withPivot('is_primary'); }
    // public function workouts(): BelongsToMany { return $this->belongsToMany(Workout::class); }
}
