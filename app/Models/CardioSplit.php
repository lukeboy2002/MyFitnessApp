<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['cardio_log_id', 'number', 'duration_seconds'])]
class CardioSplit extends Model
{
    public function cardioLog(): BelongsTo
    {
        return $this->belongsTo(CardioLog::class);
    }
}
