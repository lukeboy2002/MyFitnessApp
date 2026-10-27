<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Eén weging per dag: gewicht en eventueel vetpercentage */
#[Fillable(['measured_on', 'weight_kg', 'body_fat'])]
class BodyWeight extends Model
{
    protected function casts(): array
    {
        return [
            'measured_on' => 'date',
            'weight_kg' => 'decimal:1',
            'body_fat' => 'decimal:1',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
