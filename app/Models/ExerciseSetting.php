<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Jouw stand van een apparaat, bijv. "stoel 4, rugleuning 2"; één per oefening, van jou alleen */
#[Fillable(['exercise_id', 'body'])]
class ExerciseSetting extends Model
{
    public const MAX = 200;

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function exercise(): BelongsTo
    {
        return $this->belongsTo(Exercise::class);
    }
}
