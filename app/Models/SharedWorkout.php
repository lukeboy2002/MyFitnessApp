<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Een workout die een maatje je stuurde; openstaand tot je hem overneemt of weigert */
#[Fillable(['workout_id', 'sender_id', 'recipient_id', 'message', 'accepted_at', 'declined_at', 'copy_workout_id'])]
class SharedWorkout extends Model
{
    protected function casts(): array
    {
        return [
            'accepted_at' => 'datetime',
            'declined_at' => 'datetime',
        ];
    }

    public function workout(): BelongsTo
    {
        return $this->belongsTo(Workout::class);
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    public function recipient(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recipient_id');
    }

    public function copy(): BelongsTo
    {
        return $this->belongsTo(Workout::class, 'copy_workout_id');
    }

    public function isOpen(): bool
    {
        return $this->accepted_at === null && $this->declined_at === null;
    }

    #[Scope]
    protected function open(Builder $query): void
    {
        $query->whereNull('accepted_at')->whereNull('declined_at');
    }
}
