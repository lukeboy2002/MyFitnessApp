<?php

namespace App\Models;

use App\Models\Concerns\OwnedOrGlobal;
use Database\Factories\MuscleGroupFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

#[Fillable(['body_part_id', 'user_id', 'name', 'slug', 'color', 'icon', 'image_path', 'sort_order'])]
class MuscleGroup extends Model
{
    /** @use HasFactory<MuscleGroupFactory> */
    use HasFactory, OwnedOrGlobal;

    public function bodyPart(): BelongsTo
    {
        return $this->belongsTo(BodyPart::class);
    }

    /** Geüpload plaatje, anders storage/app/public/musclegroups/{slug}.png voor de standaardlijst */
    public function imageUrl(): ?string
    {
        $path = "musclegroups/{$this->slug}.png";

        if ($this->image_path) {
            return Storage::disk('public')->url($this->image_path);
        }

        return $this->isStandard() && Storage::disk('public')->exists($path) ? Storage::disk('public')->url($path) : null;
    }

    public function muscles(): HasMany
    {
        return $this->hasMany(Muscle::class)->orderBy('sort_order')->orderBy('name');
    }

    public function exercises(): BelongsToMany
    {
        return $this->belongsToMany(Exercise::class)->withPivot('is_primary');
    }

    // Later (stap 5):
    // public function workouts(): BelongsToMany { return $this->belongsToMany(Workout::class); }
}
