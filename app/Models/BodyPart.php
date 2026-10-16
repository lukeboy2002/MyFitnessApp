<?php

namespace App\Models;

use App\Models\Concerns\OwnedOrGlobal;
use Database\Factories\BodyPartFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

#[Fillable(['user_id', 'name', 'slug', 'icon', 'image_path', 'sort_order'])]
class BodyPart extends Model
{
    /** @use HasFactory<BodyPartFactory> */
    use HasFactory, OwnedOrGlobal;

    /** Geüpload plaatje, anders storage/app/public/bodyparts/{slug}.png voor de standaardlijst */
    public function imageUrl(): ?string
    {
        $path = "bodyparts/{$this->slug}.png";

        if ($this->image_path) {
            return Storage::disk('public')->url($this->image_path);
        }

        return $this->isStandard() && Storage::disk('public')->exists($path) ? Storage::disk('public')->url($path) : null;
    }

    public function muscleGroups(): HasMany
    {
        return $this->hasMany(MuscleGroup::class)->orderBy('sort_order')->orderBy('name');
    }
}
