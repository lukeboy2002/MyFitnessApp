<?php

namespace App\Models\Concerns;

use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Stamgegevens: user_id = null is de standaardlijst (voor iedereen zichtbaar, niet te wijzigen),
 * user_id = jouw id is een eigen item (alleen voor jou zichtbaar en te wijzigen).
 */
trait OwnedOrGlobal
{
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    #[Scope]
    protected function visibleTo(Builder $query, ?User $user): void
    {
        $query->where(fn (Builder $q) => $q->whereNull('user_id')->when($user, fn ($q) => $q->orWhere('user_id', $user->id)));
    }

    public function isStandard(): bool
    {
        return is_null($this->user_id);
    }

    public function isOwnedBy(?User $user): bool
    {
        return $user !== null && $this->user_id === $user->id;
    }

    /** Wijzigen en verwijderen: je eigen items, en als admin ook de standaardlijst */
    public function isManageableBy(?User $user): bool
    {
        return $this->isOwnedBy($user) || ($this->isStandard() && $user?->isAdmin());
    }

    /** Slug bij opslaan; standaarditems houden hun slug, want de seeders herkennen ze daaraan */
    public function slugFor(string $name): string
    {
        return $this->exists && $this->isStandard() ? $this->slug : static::uniqueSlug($name, $this->user_id, $this->id);
    }

    /** Unieke slug per gebruiker (of binnen de standaardlijst) */
    public static function uniqueSlug(string $name, ?int $userId, ?int $ignoreId = null): string
    {
        $base = str($name)->slug()->value() ?: 'item';
        $slug = $base;
        $i = 2;

        while (static::query()->where('user_id', $userId)->where('slug', $slug)->when($ignoreId, fn ($q) => $q->whereKeyNot($ignoreId))->exists()) {
            $slug = $base.'-'.$i++;
        }

        return $slug;
    }
}
