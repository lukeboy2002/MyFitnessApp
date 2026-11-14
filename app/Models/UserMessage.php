<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Een bericht van de admin in de Berichten van één gebruiker (eigen kopie: gelezen/verwijderd per gebruiker) */
#[Fillable(['user_id', 'push_broadcast_id', 'title', 'body', 'url', 'read_at'])]
class UserMessage extends Model
{
    protected $attributes = [
        'push_broadcast_id' => null,
        'url' => null,
        'read_at' => null,
    ];

    protected function casts(): array
    {
        return ['read_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function broadcast(): BelongsTo
    {
        return $this->belongsTo(PushBroadcast::class, 'push_broadcast_id');
    }

    public function markRead(): void
    {
        if (! $this->read_at) {
            $this->update(['read_at' => now()]);
        }
    }
}
