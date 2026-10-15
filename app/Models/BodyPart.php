<?php

namespace App\Models;

use App\Models\Concerns\OwnedOrGlobal;
use Database\Factories\BodyPartFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['user_id', 'name', 'slug', 'sort_order'])]
class BodyPart extends Model
{
    /** @use HasFactory<BodyPartFactory> */
    use HasFactory, OwnedOrGlobal;

    public function muscleGroups(): HasMany
    {
        return $this->hasMany(MuscleGroup::class)->orderBy('sort_order')->orderBy('name');
    }
}
