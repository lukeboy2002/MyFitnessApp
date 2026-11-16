<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Eén bericht in een contactgesprek, van de gebruiker of van een admin */
#[Fillable(['contact_request_id', 'user_id', 'is_admin', 'body'])]
class ContactReply extends Model
{
    protected $attributes = [
        'is_admin' => false,
    ];

    protected function casts(): array
    {
        return ['is_admin' => 'boolean'];
    }

    public function request(): BelongsTo
    {
        return $this->belongsTo(ContactRequest::class, 'contact_request_id');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
