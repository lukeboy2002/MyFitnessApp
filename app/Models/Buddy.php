<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Maatjes: user_id heeft buddy_id uitgenodigd; met accepted_at zijn ze maatjes (beide kanten op) */
#[Fillable(['user_id', 'buddy_id', 'accepted_at'])]
class Buddy extends Model
{
    protected function casts(): array
    {
        return [
            'accepted_at' => 'datetime',
        ];
    }

    /** Wie uitnodigde */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Wie uitgenodigd werd */
    public function buddy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'buddy_id');
    }

    public function isAccepted(): bool
    {
        return $this->accepted_at !== null;
    }

    /** De ander, gezien vanuit $user */
    public function other(User $user): User
    {
        return $this->user_id === $user->id ? $this->buddy : $this->user;
    }

    #[Scope]
    protected function accepted(Builder $query): void
    {
        $query->whereNotNull('accepted_at');
    }

    #[Scope]
    protected function pending(Builder $query): void
    {
        $query->whereNull('accepted_at');
    }

    /** Rijen waar $user aan een van beide kanten staat */
    #[Scope]
    protected function involving(Builder $query, User $user): void
    {
        $query->where(fn (Builder $q) => $q->where('user_id', $user->id)->orWhere('buddy_id', $user->id));
    }

    /** De rij tussen twee gebruikers, in welke richting dan ook */
    public static function between(User $a, User $b): ?self
    {
        return static::where(fn (Builder $q) => $q->where('user_id', $a->id)->where('buddy_id', $b->id))
            ->orWhere(fn (Builder $q) => $q->where('user_id', $b->id)->where('buddy_id', $a->id))
            ->first();
    }

    public static function areBuddies(User $a, User $b): bool
    {
        return (bool) static::between($a, $b)?->isAccepted();
    }
}
