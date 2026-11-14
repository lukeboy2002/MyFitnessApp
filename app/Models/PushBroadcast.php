<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Bericht van de admin aan iedereen of aan één gebruiker: in Berichten (UserMessage) en als pushmelding (Admin\PushMessages, Job SendPushBroadcast) */
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

    public function messages(): HasMany
    {
        return $this->hasMany(UserMessage::class);
    }

    /** Wie het bericht in Berichten krijgt: iedereen (of die ene gebruiker) behalve geblokkeerden */
    public static function recipients(?int $recipientId = null): Builder
    {
        return User::query()
            ->when($recipientId, fn ($q) => $q->whereKey($recipientId))
            ->where(fn ($q) => $q->whereNull('banned_at')->orWhere('banned_until', '<=', now()));
    }

    /** Wie er ook een pushmelding van krijgt: pushmeldingen aan en berichten van de beheerder aan */
    public static function audience(?int $recipientId = null): Builder
    {
        return self::recipients($recipientId)->whereHas('pushSubscriptions')->where('push_admin', true);
    }
}
