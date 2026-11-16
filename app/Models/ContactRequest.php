<?php

namespace App\Models;

use App\Enums\ContactStatus;
use App\Enums\ContactTopic;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/** Een vraag van een gebruiker aan de admin (Contact), met de berichten heen en weer in replies */
#[Fillable(['user_id', 'topic', 'subject', 'status', 'user_seen_at'])]
class ContactRequest extends Model
{
    protected $attributes = [
        'status' => 'open',
        'user_seen_at' => null,
    ];

    protected function casts(): array
    {
        return [
            'topic' => ContactTopic::class,
            'status' => ContactStatus::class,
            'user_seen_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function replies(): HasMany
    {
        return $this->hasMany(ContactReply::class)->orderBy('id');
    }

    public function latestReply(): HasOne
    {
        return $this->hasOne(ContactReply::class)->latestOfMany();
    }

    /** Wacht op de admin */
    #[Scope]
    protected function open(Builder $query): void
    {
        $query->where('status', ContactStatus::Open);
    }

    /** Heeft de admin geantwoord sinds de gebruiker het gesprek voor het laatst zag? */
    public function hasUnreadAnswer(): bool
    {
        $reply = $this->latestReply;

        return $reply?->is_admin && (! $this->user_seen_at || $this->user_seen_at < $reply->created_at);
    }

    /** Bericht toevoegen; de status volgt wie er als laatste iets zei */
    public function addReply(User $author, string $body, bool $asAdmin = false): ContactReply
    {
        $reply = $this->replies()->create(['user_id' => $author->id, 'is_admin' => $asAdmin, 'body' => trim($body)]);

        $this->status = $asAdmin ? ContactStatus::Answered : ContactStatus::Open;
        if (! $asAdmin) {
            $this->user_seen_at = now();
        }
        $this->touch();
        $this->unsetRelation('latestReply');

        return $reply;
    }
}
