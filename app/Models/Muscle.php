<?php

namespace App\Models;

use App\Models\Concerns\OwnedOrGlobal;
use Database\Factories\MuscleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['muscle_group_id', 'user_id', 'name', 'latin_name', 'slug', 'sort_order'])]
class Muscle extends Model
{
    /** @use HasFactory<MuscleFactory> */
    use HasFactory, OwnedOrGlobal;

    public function muscleGroup(): BelongsTo
    {
        return $this->belongsTo(MuscleGroup::class);
    }
}
