<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Een telefoon of browser waarop je pushmeldingen hebt aangezet (App\Support\WebPush) */
#[Fillable(['endpoint', 'endpoint_hash', 'public_key', 'auth_token', 'content_encoding'])]
class PushSubscription extends Model
{
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public static function hash(string $endpoint): string
    {
        return hash('sha256', $endpoint);
    }
}
