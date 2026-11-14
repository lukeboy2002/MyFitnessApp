<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Pushmelding van de admin aan iedereen of aan één gebruiker (Admin\PushMessages, Job SendPushBroadcast) */
#[Fillable(['user_id', 'recipient_id', 'to_everyone', 'recipient_name', 'title', 'body', 'url', 'users_count', 'delivered_count', 'sent_at'])]
class PushBroadcast extends Model
{
    /** Pagina's waar een tik op de melding naartoe kan (routenaam => label) */
    public const LINKS = [
        'dashboard' => 'Dashboard',
        'workouts.index' => 'Workouts',
        'calendar' => 'Calendar (page)',
        'progress.index' => 'Progress',
        'badges' => 'Badges',
        'profile.edit' => 'Profile',
    ];

    protected $attributes = [
        'recipient_id' => null,
        'to_everyone' => true,
        'recipient_name' => null,
        'url' => null,
        'users_count' => 0,
        'delivered_count' => 0,
        'sent_at' => null,
    ];

    protected function casts(): array
    {
        return [
            'to_everyone' => 'boolean',
            'sent_at' => 'datetime',
        ];
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function recipient(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recipient_id');
    }

    /** Gebruikers die de melding krijgen: pushmeldingen aan, berichten van de beheerder aan en niet geblokkeerd */
    public static function audience(?int $recipientId = null): Builder
    {
        return User::query()
            ->whereHas('pushSubscriptions')
            ->where('push_admin', true)
            ->when($recipientId, fn ($q) => $q->whereKey($recipientId))
            ->where(fn ($q) => $q->whereNull('banned_at')->orWhere('banned_until', '<=', now()));
    }
}
